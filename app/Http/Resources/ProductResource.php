<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'unit_id' => $this->unit_id,
            'category' => $this->whenLoaded('category'),
            'brand' => $this->whenLoaded('brand'),
            'unit' => $this->whenLoaded('unit'),
            'cost_price' => (float) $this->cost_price,
            'selling_price' => (float) $this->selling_price,
            'tax_rate' => (float) $this->tax_rate,
            'track_stock' => (bool) $this->track_stock,
            'stock_quantity' => (float) $this->stock_quantity,
            'alert_quantity' => (float) $this->alert_quantity,
            'image' => $this->imageUrl(),
            'is_active' => (bool) $this->is_active,
            'is_low_stock' => $this->isLowStock(),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(function ($variant) {
                $data = (new ProductVariantResource($variant))->resolve(request());
                $data['image'] = $variant->displayImageUrl($this->resource);
                $data['effective_selling_price'] = $variant->effectiveSellingPrice($this->resource);
                $data['effective_cost_price'] = $variant->effectiveCostPrice($this->resource);

                return $data;
            })->values()),
            'created_at' => $this->created_at,
        ];
    }
}
