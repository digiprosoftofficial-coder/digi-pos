<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * Create a completed (or held) sale from a cart payload.
     *
     * Expected $data shape:
     * [
     *   'customer_id' => ?int,
     *   'branch_id' => ?int,
     *   'discount' => float,          // cart-level discount amount
     *   'tax' => ?float,              // optional override; otherwise summed from items
     *   'paid' => float,
     *   'payment_method' => string,   // cash|card|mobile|bank
     *   'note' => ?string,
     *   'status' => 'completed'|'held',
     *   'client_uuid' => ?string,     // offline idempotency key
     *   'items' => [ ['product_id'=>int,'variant_id'=>?int,'quantity'=>float,'unit_price'=>?float,'discount'=>?float], ... ],
     * ]
     */
    public function checkout(array $data, ?int $userId = null): Sale
    {
        // Idempotency for offline sync: return existing sale if already recorded.
        if (! empty($data['client_uuid'])) {
            $existing = Sale::where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return $existing->load('items', 'payments');
            }
        }

        if (empty($data['items'])) {
            throw ValidationException::withMessages(['items' => ['Cart is empty.']]);
        }

        return DB::transaction(function () use ($data, $userId) {
            $storeId = StoreContext::id();
            $cartDiscount = (float) ($data['discount'] ?? 0);
            $status = $data['status'] ?? 'completed';

            $lineItems = [];
            $subtotal = 0;
            $taxTotal = 0;

            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $variant = null;

                if (! empty($row['variant_id'])) {
                    $variant = ProductVariant::where('product_id', $product->id)
                        ->findOrFail($row['variant_id']);
                }

                $qty = (float) ($row['quantity'] ?? 1);
                if ($qty <= 0) {
                    throw ValidationException::withMessages(['items' => ['Quantity must be greater than zero.']]);
                }

                $unitPrice = isset($row['unit_price'])
                    ? (float) $row['unit_price']
                    : ($variant
                        ? $variant->effectiveSellingPrice($product)
                        : (float) $product->selling_price);

                $unitCost = $variant
                    ? $variant->effectiveCostPrice($product)
                    : (float) $product->cost_price;
                $lineDiscount = (float) ($row['discount'] ?? 0);
                $lineBase = ($unitPrice * $qty) - $lineDiscount;
                $lineTax = round($lineBase * ((float) $product->tax_rate) / 100, 2);

                $subtotal += $unitPrice * $qty - $lineDiscount;
                $taxTotal += $lineTax;

                $lineItems[] = [
                    'model_product' => $product,
                    'model_variant' => $variant,
                    'data' => [
                        'store_id' => $storeId,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'name' => $variant ? $product->name.' - '.$variant->name : $product->name,
                        'sku' => $variant->sku ?? $product->sku,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'discount' => $lineDiscount,
                        'tax' => $lineTax,
                        'subtotal' => round($lineBase + $lineTax, 2),
                    ],
                ];
            }

            $taxTotal = isset($data['tax']) ? (float) $data['tax'] : $taxTotal;

            // Loyalty: redeem points as a discount, earn points on the net total.
            $loyalty = $this->loyaltyConfig($storeId);
            $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
            $pointsRedeemed = 0;
            $redeemValue = 0.0;

            if ($loyalty && $customer && ! empty($data['points_redeem'])) {
                $available = (int) $customer->loyalty_points;
                $pointsRedeemed = max(0, min((int) $data['points_redeem'], $available));
                $redeemValue = round($pointsRedeemed * (float) $loyalty['redeem_value'], 2);
            }

            $effectiveDiscount = round($cartDiscount + $redeemValue, 2);
            $total = round($subtotal + $taxTotal - $effectiveDiscount, 2);
            if ($total < 0) {
                $total = 0;
            }
            $paid = (float) ($data['paid'] ?? ($status === 'completed' ? $total : 0));
            $change = max(0, round($paid - $total, 2));
            $due = max(0, round($total - $paid, 2));

            $paymentStatus = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due');

            $pointsEarned = ($status === 'completed' && $loyalty && $customer)
                ? (int) floor($total * (float) $loyalty['earn_per_unit'])
                : 0;

            $sale = Sale::create([
                'store_id' => $storeId,
                'branch_id' => $data['branch_id'] ?? null,
                'user_id' => $userId,
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_no' => $this->nextInvoiceNo($storeId),
                'subtotal' => round($subtotal, 2),
                'discount' => $effectiveDiscount,
                'tax' => round($taxTotal, 2),
                'total' => $total,
                'paid' => $paid,
                'change' => $change,
                'due' => $due,
                'points_earned' => $pointsEarned,
                'points_redeemed' => $pointsRedeemed,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'note' => $data['note'] ?? null,
                'client_uuid' => $data['client_uuid'] ?? null,
                'sold_at' => now(),
            ]);

            foreach ($lineItems as $line) {
                $sale->items()->create($line['data']);

                if ($status === 'completed') {
                    $this->decrementStock(
                        $line['model_product'],
                        $line['model_variant'],
                        (float) $line['data']['quantity'],
                        $sale->invoice_no,
                        $userId,
                        $data['branch_id'] ?? null
                    );
                }
            }

            if ($status === 'completed' && $paid > 0) {
                $sale->payments()->create([
                    'store_id' => $storeId,
                    'method' => $data['payment_method'] ?? 'cash',
                    'amount' => $paid,
                    'paid_at' => now(),
                ]);
            }

            if ($sale->customer_id && $due > 0) {
                $sale->customer()->increment('due_balance', $due);
            }

            // Apply loyalty point movements to the customer balance.
            if ($customer && ($pointsEarned > 0 || $pointsRedeemed > 0)) {
                $customer->loyalty_points = max(0, (int) $customer->loyalty_points + $pointsEarned - $pointsRedeemed);
                $customer->save();
            }

            return $sale->load('items', 'payments', 'customer');
        });
    }

    protected function decrementStock(Product $product, ?ProductVariant $variant, float $qty, string $reference, ?int $userId, ?int $branchId = null): void
    {
        if (! $product->track_stock) {
            return;
        }

        if ($variant) {
            $variant->decrement('stock_quantity', $qty);
            $balance = $variant->fresh()->stock_quantity;
        } else {
            $product->decrement('stock_quantity', $qty);
            $balance = $product->fresh()->stock_quantity;
        }

        StockMovement::create([
            'store_id' => $product->store_id,
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'type' => 'sale',
            'quantity' => -$qty,
            'balance_after' => $balance,
            'reference' => $reference,
            'user_id' => $userId,
        ]);
    }

    protected function nextInvoiceNo(int $storeId): string
    {
        $count = Sale::where('store_id', $storeId)->count() + 1;

        return 'INV-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve loyalty settings for the store, or null when disabled.
     *
     * @return array{earn_per_unit: float, redeem_value: float}|null
     */
    protected function loyaltyConfig(int $storeId): ?array
    {
        $settings = Store::find($storeId)?->settings['loyalty'] ?? null;

        if (! $settings || empty($settings['enabled'])) {
            return null;
        }

        return [
            'earn_per_unit' => (float) ($settings['earn_per_unit'] ?? 0),
            'redeem_value' => (float) ($settings['redeem_value'] ?? 0),
        ];
    }
}
