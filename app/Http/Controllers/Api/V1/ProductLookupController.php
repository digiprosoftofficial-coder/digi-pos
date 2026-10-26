<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductLookupController extends Controller
{
    use ApiResponses;

    /**
     * Exact lookup by barcode or SKU (for scanner / USB input).
     * Returns the product and, if matched, the specific variant.
     */
    public function scan(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return $this->error('No code provided.', 422);
        }

        // Try variant barcode/sku first.
        $variant = ProductVariant::where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))->first();

        if ($variant) {
            $product = $variant->product()->with(['unit', 'variants'])->first();

            return $this->ok([
                'product' => new ProductResource($product),
                'variant_id' => $variant->id,
            ]);
        }

        $product = Product::with(['unit', 'variants'])
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        if (! $product) {
            return $this->error('Product not found.', 404);
        }

        return $this->ok([
            'product' => new ProductResource($product),
            'variant_id' => null,
        ]);
    }

    /**
     * Full active catalog for offline caching (available to all POS users,
     * not gated behind the products capability so cashiers can sell offline).
     */
    public function catalog(Request $request): JsonResponse
    {
        $products = Product::with(['unit', 'variants'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->ok(
            ProductResource::collection($products)->resolve(),
            '',
            ['synced_at' => now()->toIso8601String()]
        );
    }

    /**
     * Fast partial search for the POS manual search box.
     */
    public function search(Request $request): JsonResponse
    {
        $s = trim((string) $request->query('q', ''));

        $products = Product::with(['unit', 'variants'])
            ->where('is_active', true)
            ->when($s !== '', function ($q) use ($s) {
                $q->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                        ->orWhere('sku', 'like', "%{$s}%")
                        ->orWhere('barcode', 'like', "%{$s}%");
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get();

        return $this->ok(ProductResource::collection($products)->resolve());
    }
}
