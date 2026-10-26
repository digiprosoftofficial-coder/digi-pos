<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;

class InventoryService
{
    /**
     * Apply a stock change and record a ledger movement.
     *
     * @param  float  $qty  Positive to add stock (purchase/return-in), negative to remove.
     * @param  string  $type  sale|purchase|return|adjustment
     */
    public function adjust(
        Product $product,
        ?ProductVariant $variant,
        float $qty,
        string $type,
        ?string $reference = null,
        ?int $branchId = null,
        ?int $userId = null
    ): void {
        if (! $product->track_stock || $qty == 0.0) {
            return;
        }

        if ($variant) {
            $variant->increment('stock_quantity', $qty);
            $balance = $variant->fresh()->stock_quantity;
        } else {
            $product->increment('stock_quantity', $qty);
            $balance = $product->fresh()->stock_quantity;
        }

        StockMovement::create([
            'store_id' => $product->store_id,
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'type' => $type,
            'quantity' => $qty,
            'balance_after' => $balance,
            'reference' => $reference,
            'user_id' => $userId,
        ]);
    }
}
