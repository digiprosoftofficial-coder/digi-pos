<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Support\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * Process a return against a sale.
     *
     * $data:
     *  sale_id, refund_method(cash|due_adjust|store_credit), restock(bool), note?,
     *  items: [ [sale_item_id, quantity], ... ]
     */
    public function create(array $data, ?int $userId = null): SaleReturn
    {
        if (empty($data['items'])) {
            throw ValidationException::withMessages(['items' => ['Select at least one item to return.']]);
        }

        return DB::transaction(function () use ($data, $userId) {
            $storeId = StoreContext::id();
            $sale = Sale::with('items')->findOrFail($data['sale_id']);
            $restock = (bool) ($data['restock'] ?? true);

            $subtotal = 0;
            $taxTotal = 0;
            $prepared = [];

            foreach ($data['items'] as $row) {
                $saleItem = SaleItem::where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);
                $qty = (float) ($row['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                // Guard against returning more than was sold (net of prior returns).
                $alreadyReturned = (float) DB::table('sale_return_items')
                    ->where('sale_item_id', $saleItem->id)
                    ->sum('quantity');
                $sellable = (float) $saleItem->quantity - $alreadyReturned;
                if ($qty > $sellable) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot return more than {$sellable} of {$saleItem->name}."],
                    ]);
                }

                $unitPrice = (float) $saleItem->unit_price;
                $perUnitTax = $saleItem->quantity > 0 ? ((float) $saleItem->tax / (float) $saleItem->quantity) : 0;
                $lineSubtotal = round($unitPrice * $qty, 2);
                $lineTax = round($perUnitTax * $qty, 2);
                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;

                $prepared[] = compact('saleItem', 'qty', 'unitPrice', 'lineSubtotal', 'lineTax');
            }

            if (empty($prepared)) {
                throw ValidationException::withMessages(['items' => ['Nothing to return.']]);
            }

            $total = round($subtotal + $taxTotal, 2);

            $return = SaleReturn::create([
                'store_id' => $storeId,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'user_id' => $userId,
                'reference' => $this->nextReference($storeId),
                'subtotal' => round($subtotal, 2),
                'tax' => round($taxTotal, 2),
                'total' => $total,
                'refund_method' => $data['refund_method'] ?? 'cash',
                'restock' => $restock,
                'note' => $data['note'] ?? null,
                'returned_at' => now(),
            ]);

            foreach ($prepared as $line) {
                $saleItem = $line['saleItem'];
                $return->items()->create([
                    'store_id' => $storeId,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'name' => $saleItem->name,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['unitPrice'],
                    'subtotal' => round($line['lineSubtotal'] + $line['lineTax'], 2),
                ]);

                if ($restock && $saleItem->product_id) {
                    $product = Product::find($saleItem->product_id);
                    if ($product) {
                        $variant = $saleItem->product_variant_id
                            ? ProductVariant::find($saleItem->product_variant_id)
                            : null;
                        $this->inventory->adjust(
                            $product,
                            $variant,
                            $line['qty'],
                            'return',
                            $return->reference,
                            $sale->branch_id,
                            $userId
                        );
                    }
                }
            }

            // Track returned total on the sale and update its status.
            $sale->increment('returned_total', $total);
            $sale->refresh();
            if ((float) $sale->returned_total >= (float) $sale->total) {
                $sale->update(['status' => 'refunded']);
            }

            // Refund handling.
            if (($data['refund_method'] ?? 'cash') === 'due_adjust' && $sale->customer_id) {
                $sale->customer()->decrement('due_balance', $total);
                if ($sale->customer->fresh()->due_balance < 0) {
                    $sale->customer->update(['due_balance' => 0]);
                }
            }

            return $return->load('items', 'customer');
        });
    }

    protected function nextReference(int $storeId): string
    {
        $count = SaleReturn::where('store_id', $storeId)->count() + 1;

        return 'RET-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
