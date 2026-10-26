<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\EnsuresStoreOwnership;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\PlanLimitService;
use App\Services\SkuGenerator;
use App\Support\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use ApiResponses, EnsuresStoreOwnership;

    public function __construct(
        protected InventoryService $inventory,
        protected SkuGenerator $skus,
        protected PlanLimitService $planLimits,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['category', 'brand', 'unit', 'variants'])
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                        ->orWhere('sku', 'like', "%{$s}%")
                        ->orWhere('barcode', 'like', "%{$s}%");
                });
            })
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('stock_quantity', '<=', 'alert_quantity')->where('track_stock', true))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->ok(
            ProductResource::collection($products)->resolve(),
            '',
            ['pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]]
        );
    }

    public function show(Product $product): JsonResponse
    {
        $this->ensureSameStore($product);

        return $this->ok(new ProductResource($product->load(['category', 'brand', 'unit', 'variants'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $this->planLimits->assertCanAddProduct((int) StoreContext::id());

        $product = DB::transaction(function () use ($data) {
            $variants = $data['variants'] ?? [];
            unset($data['variants']);

            $data['sku'] = $this->skus->next($data['sku'] ?? null);
            $product = Product::create($data);

            foreach (array_values($variants) as $index => $variant) {
                $product->variants()->create([
                    'store_id' => $product->store_id,
                    'name' => $variant['name'],
                    'sku' => $this->skus->resolveVariantSku($product->sku, $index + 1, $variant['sku'] ?? null),
                    'barcode' => $variant['barcode'] ?? null,
                    'cost_price' => $variant['cost_price'] ?? 0,
                    'selling_price' => $variant['selling_price'] ?? 0,
                    'stock_quantity' => $variant['stock_quantity'] ?? 0,
                    'variant_attributes' => $variant['attributes'] ?? null,
                    'is_active' => $variant['is_active'] ?? true,
                ]);
            }

            return $product;
        });

        return $this->created(new ProductResource($product->refresh()->load(['category', 'brand', 'unit', 'variants'])));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->ensureSameStore($product);

        $data = $this->validateData($request);
        $variants = array_key_exists('variants', $data) ? $data['variants'] : null;
        unset($data['variants']);

        $incomingSku = trim((string) ($data['sku'] ?? ''));
        $data['sku'] = $this->skus->next($incomingSku !== '' ? $incomingSku : $product->sku);

        DB::transaction(function () use ($product, $data, $variants): void {
            $product->update($data);
            $product->refresh();

            if ($product->type !== 'variable') {
                $product->variants()->delete();

                return;
            }

            if ($variants !== null) {
                $this->syncVariants($product, $variants);
            }
        });

        return $this->ok(new ProductResource($product->fresh()->load(['category', 'brand', 'unit', 'variants'])));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->ensureSameStore($product);

        $product->delete();

        return $this->ok(null, 'Deleted.');
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        abort_unless((int) $product->store_id === (int) StoreContext::id(), 404);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $this->deleteStoredPath($product->image);

        $path = $request->file('image')->store(
            'products/'.$product->store_id,
            'public'
        );

        $product->update(['image' => $path]);

        return $this->ok(
            new ProductResource($product->fresh()->load(['category', 'brand', 'unit', 'variants'])),
            'Image uploaded.'
        );
    }

    public function deleteImage(Product $product): JsonResponse
    {
        abort_unless((int) $product->store_id === (int) StoreContext::id(), 404);

        $this->deleteStoredPath($product->image);
        $product->update(['image' => null]);

        return $this->ok(
            new ProductResource($product->fresh()->load(['category', 'brand', 'unit', 'variants'])),
            'Image removed.'
        );
    }

    public function uploadVariantImage(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless((int) $product->store_id === (int) StoreContext::id(), 404);
        abort_unless((int) $variant->product_id === (int) $product->id, 404);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $this->deleteStoredPath($variant->image);

        $path = $request->file('image')->store(
            'products/'.$product->store_id.'/variants',
            'public'
        );

        $variant->update(['image' => $path]);

        return $this->ok(
            new ProductResource($product->fresh()->load(['category', 'brand', 'unit', 'variants'])),
            'Variant image uploaded.'
        );
    }

    public function deleteVariantImage(Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless((int) $product->store_id === (int) StoreContext::id(), 404);
        abort_unless((int) $variant->product_id === (int) $product->id, 404);

        $this->deleteStoredPath($variant->image);
        $variant->update(['image' => null]);

        return $this->ok(
            new ProductResource($product->fresh()->load(['category', 'brand', 'unit', 'variants'])),
            'Variant image removed.'
        );
    }

    /**
     * Manually add or remove stock and record a ledger movement.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $this->ensureSameStore($product);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $variant = null;
        if (! empty($data['variant_id'])) {
            $variant = $product->variants()->findOrFail($data['variant_id']);
        }

        $this->inventory->adjust(
            $product,
            $variant,
            (float) $data['quantity'],
            'adjustment',
            $data['reason'] ?? 'manual',
            $data['branch_id'] ?? null,
            $request->user()->id
        );

        return $this->ok(
            new ProductResource($product->fresh()->load(['category', 'brand', 'unit', 'variants'])),
            'Stock adjusted.'
        );
    }

    public function stockMovements(Product $product): JsonResponse
    {
        $this->ensureSameStore($product);

        $movements = StockMovement::query()
            ->where('product_id', $product->id)
            ->latest('id')
            ->limit(30)
            ->get();

        return $this->ok($movements);
    }

    /**
     * Create, update, and drop variants so the payload matches persisted rows.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    protected function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];

        foreach (array_values($variants) as $index => $variant) {
            $existing = null;
            if (! empty($variant['id'])) {
                $existing = $product->variants()->find($variant['id']);
            }

            $payload = [
                'name' => $variant['name'],
                'sku' => $this->skus->resolveVariantSku(
                    $product->sku,
                    $index + 1,
                    $variant['sku'] ?? null,
                    $existing?->sku
                ),
                'barcode' => $variant['barcode'] ?? $existing?->barcode,
                'cost_price' => $variant['cost_price'] ?? $existing?->cost_price ?? 0,
                'selling_price' => $variant['selling_price'] ?? 0,
                'stock_quantity' => $variant['stock_quantity'] ?? $existing?->stock_quantity ?? 0,
                'variant_attributes' => $variant['attributes'] ?? $existing?->variant_attributes,
                'is_active' => $variant['is_active'] ?? true,
            ];

            if ($existing) {
                $existing->update($payload);
                $keptIds[] = $existing->id;

                continue;
            }

            $payload['store_id'] = $product->store_id;
            $created = $product->variants()->create($payload);
            $keptIds[] = $created->id;
        }

        $removed = $product->variants()->whereNotIn('id', $keptIds)->get();
        foreach ($removed as $variant) {
            $this->deleteStoredPath($variant->image);
        }
        $product->variants()->whereNotIn('id', $keptIds)->delete();
    }

    protected function deleteStoredPath(?string $path): void
    {
        if (! is_string($path) || $path === '' || str_contains($path, '://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:simple,variable'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'track_stock' => ['boolean'],
            'stock_quantity' => ['nullable', 'numeric'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'variants' => ['array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:150'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['nullable', 'numeric'],
            'variants.*.attributes' => ['nullable', 'array'],
        ]);
    }
}
