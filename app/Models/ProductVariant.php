<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    use BelongsToStore;

    protected $guarded = [];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'variant_attributes' => 'array',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function imageUrl(): ?string
    {
        if (! is_string($this->image) || $this->image === '') {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function displayImageUrl(Product $product): ?string
    {
        return $this->imageUrl() ?? $product->imageUrl();
    }

    public function effectiveSellingPrice(Product $product): float
    {
        $own = (float) $this->selling_price;

        if ($own > 0) {
            return $own;
        }

        return (float) $product->selling_price;
    }

    public function effectiveCostPrice(Product $product): float
    {
        $own = (float) $this->cost_price;

        if ($own > 0) {
            return $own;
        }

        return (float) $product->cost_price;
    }
}
