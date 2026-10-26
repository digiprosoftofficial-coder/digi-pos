<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

class InventoryValuationService
{
    /**
     * @return array{
     *     valuation_cost: float,
     *     valuation_retail: float,
     *     potential_profit: float,
     *     products_tracked: int,
     *     low_stock_count: int,
     *     low_stock: list<array<string, mixed>>,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function summarize(): array
    {
        $products = Product::query()
            ->where('track_stock', true)
            ->with('variants')
            ->orderBy('name')
            ->get();

        $valuationCost = 0.0;
        $valuationRetail = 0.0;
        $lowStock = [];
        $lines = [];

        foreach ($products as $product) {
            if ($product->isVariable() && $product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $this->accumulateLine(
                        $product,
                        $variant,
                        $valuationCost,
                        $valuationRetail,
                        $lowStock,
                        $lines
                    );
                }

                continue;
            }

            $this->accumulateLine($product, null, $valuationCost, $valuationRetail, $lowStock, $lines);
        }

        usort($lowStock, fn (array $a, array $b) => $a['stock_quantity'] <=> $b['stock_quantity']);

        return [
            'valuation_cost' => round($valuationCost, 2),
            'valuation_retail' => round($valuationRetail, 2),
            'potential_profit' => round($valuationRetail - $valuationCost, 2),
            'products_tracked' => $products->count(),
            'low_stock_count' => count($lowStock),
            'low_stock' => array_slice($lowStock, 0, 100),
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lowStock
     * @param  list<array<string, mixed>>  $lines
     */
    protected function accumulateLine(
        Product $product,
        ?ProductVariant $variant,
        float &$valuationCost,
        float &$valuationRetail,
        array &$lowStock,
        array &$lines,
    ): void {
        $qty = $variant
            ? (float) $variant->stock_quantity
            : (float) $product->stock_quantity;

        $cost = $variant
            ? $variant->effectiveCostPrice($product)
            : (float) $product->cost_price;

        $retail = $variant
            ? $variant->effectiveSellingPrice($product)
            : (float) $product->selling_price;

        $valuationCost += $qty * $cost;
        $valuationRetail += $qty * $retail;

        $alert = (float) $product->alert_quantity;
        $name = $variant ? $product->name.' - '.$variant->name : $product->name;
        $sku = $variant?->sku ?? $product->sku;
        $lineId = $variant ? 'variant-'.$variant->id : 'product-'.$product->id;

        $lines[] = [
            'id' => $lineId,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'name' => $name,
            'sku' => $sku,
            'stock_quantity' => $qty,
            'cost_price' => $cost,
            'selling_price' => $retail,
            'stock_value_cost' => round($qty * $cost, 2),
        ];

        if ($qty <= $alert) {
            $lowStock[] = [
                'id' => $lineId,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $name,
                'sku' => $sku,
                'stock_quantity' => $qty,
                'alert_quantity' => $alert,
            ];
        }
    }
}
