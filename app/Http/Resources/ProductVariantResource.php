<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'cost_price' => (float) $this->cost_price,
            'selling_price' => (float) $this->selling_price,
            'stock_quantity' => (float) $this->stock_quantity,
            'attributes' => $this->variant_attributes ?? [],
            'is_active' => (bool) $this->is_active,
            'own_image' => $this->imageUrl(),
        ];
    }
}
