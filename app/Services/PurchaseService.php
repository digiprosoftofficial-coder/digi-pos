<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * Record a purchase. When status = received, stock is added and the
     * product cost price is refreshed to the latest purchase cost.
     *
     * $data:
     *  supplier_id?, branch_id?, supplier_invoice?, discount?, tax?, shipping?,
     *  paid?, status(received|pending), note?, update_cost(bool),
     *  items: [ [product_id, variant_id?, quantity, unit_cost, discount?], ... ]
     */
    public function create(array $data, ?int $userId = null): Purchase
    {
        if (empty($data['items'])) {
            throw ValidationException::withMessages(['items' => ['Add at least one item.']]);
        }

        return DB::transaction(function () use ($data, $userId) {
            $storeId = StoreContext::id();
            $status = $data['status'] ?? 'received';
            $updateCost = (bool) ($data['update_cost'] ?? true);

            $subtotal = 0;
            $prepared = [];

            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $variant = ! empty($row['variant_id'])
                    ? ProductVariant::where('product_id', $product->id)->findOrFail($row['variant_id'])
                    : null;

                $qty = (float) ($row['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw ValidationException::withMessages(['items' => ['Quantity must be greater than zero.']]);
                }
                $unitCost = (float) ($row['unit_cost'] ?? 0);
                $lineDiscount = (float) ($row['discount'] ?? 0);
                $lineSubtotal = round($unitCost * $qty - $lineDiscount, 2);
                $subtotal += $lineSubtotal;

                $prepared[] = compact('product', 'variant', 'qty', 'unitCost', 'lineDiscount', 'lineSubtotal');
            }

            $discount = (float) ($data['discount'] ?? 0);
            $tax = (float) ($data['tax'] ?? 0);
            $shipping = (float) ($data['shipping'] ?? 0);
            $total = round($subtotal - $discount + $tax + $shipping, 2);
            $paid = (float) ($data['paid'] ?? 0);
            $due = max(0, round($total - $paid, 2));
            $paymentStatus = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due');

            $purchase = Purchase::create([
                'store_id' => $storeId,
                'branch_id' => $data['branch_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => $userId,
                'reference' => $this->nextReference($storeId),
                'supplier_invoice' => $data['supplier_invoice'] ?? null,
                'subtotal' => round($subtotal, 2),
                'discount' => $discount,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
                'paid' => $paid,
                'due' => $due,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'note' => $data['note'] ?? null,
                'purchased_at' => now(),
            ]);

            foreach ($prepared as $line) {
                $purchase->items()->create([
                    'store_id' => $storeId,
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id,
                    'name' => $line['variant'] ? $line['product']->name.' - '.$line['variant']->name : $line['product']->name,
                    'quantity' => $line['qty'],
                    'unit_cost' => $line['unitCost'],
                    'discount' => $line['lineDiscount'],
                    'subtotal' => $line['lineSubtotal'],
                ]);

                if ($status === 'received') {
                    $this->inventory->adjust(
                        $line['product'],
                        $line['variant'],
                        $line['qty'],
                        'purchase',
                        $purchase->reference,
                        $purchase->branch_id,
                        $userId
                    );

                    if ($updateCost && $line['unitCost'] > 0) {
                        if ($line['variant']) {
                            $line['variant']->update(['cost_price' => $line['unitCost']]);
                        } else {
                            $line['product']->update(['cost_price' => $line['unitCost']]);
                        }
                    }
                }
            }

            // Supplier ledger: record payment + due.
            if ($purchase->supplier_id) {
                $supplier = Supplier::findOrFail($purchase->supplier_id);
                if ($due > 0) {
                    $supplier->increment('due_balance', $due);
                }
                if ($paid > 0) {
                    $purchase->payments()->create([
                        'store_id' => $storeId,
                        'supplier_id' => $supplier->id,
                        'user_id' => $userId,
                        'method' => $data['payment_method'] ?? 'cash',
                        'amount' => $paid,
                        'paid_at' => now(),
                    ]);
                }
            }

            return $purchase->load('items', 'supplier');
        });
    }

    /** Pay down a supplier's outstanding balance. */
    public function paySupplier(Supplier $supplier, array $data, ?int $userId = null): void
    {
        DB::transaction(function () use ($supplier, $data, $userId) {
            $amount = (float) $data['amount'];
            $supplier->payments()->create([
                'store_id' => $supplier->store_id,
                'supplier_id' => $supplier->id,
                'purchase_id' => $data['purchase_id'] ?? null,
                'user_id' => $userId,
                'method' => $data['method'] ?? 'cash',
                'amount' => $amount,
                'note' => $data['note'] ?? null,
                'paid_at' => now(),
            ]);
            $supplier->decrement('due_balance', $amount);
            if ($supplier->fresh()->due_balance < 0) {
                $supplier->update(['due_balance' => 0]);
            }
        });
    }

    protected function nextReference(int $storeId): string
    {
        $count = Purchase::where('store_id', $storeId)->count() + 1;

        return 'PO-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
