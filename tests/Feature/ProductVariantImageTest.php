<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Support\StoreContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_uploads_variant_image_and_returns_own_image_url(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        [$productId, $variantId] = $this->createVariableProduct($token);

        $response = $this->withToken($token)->post("/api/v1/products/{$productId}/variants/{$variantId}/image", [
            'image' => UploadedFile::fake()->image('red.jpg', 200, 200),
        ]);

        $response->assertOk();

        $variant = ProductVariant::find($variantId);
        $this->assertNotNull($variant->image);
        Storage::disk('public')->assertExists($variant->image);

        $variants = collect($response->json('data.variants'));
        $row = $variants->firstWhere('id', $variantId);
        $this->assertStringContainsString('/storage/products/', $row['own_image']);
        $this->assertSame($row['own_image'], $row['image']);
    }

    public function test_variant_image_falls_back_to_parent_product_image(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        [$productId, $variantId] = $this->createVariableProduct($token);

        $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('parent.jpg'),
        ])->assertOk();

        $response = $this->withToken($token)->getJson("/api/v1/products/{$productId}");
        $row = collect($response->json('data.variants'))->firstWhere('id', $variantId);

        $this->assertNull($row['own_image']);
        $this->assertStringContainsString('/storage/products/', $row['image']);
    }

    public function test_variant_own_image_overrides_parent_in_api_response(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        [$productId, $variantId] = $this->createVariableProduct($token);

        $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('parent.jpg'),
        ])->assertOk();

        $this->withToken($token)->post("/api/v1/products/{$productId}/variants/{$variantId}/image", [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])->assertOk();

        $response = $this->withToken($token)->getJson("/api/v1/products/{$productId}");
        $row = collect($response->json('data.variants'))->firstWhere('id', $variantId);

        $this->assertNotSame($response->json('data.image'), $row['own_image']);
        $this->assertSame($row['own_image'], $row['image']);
    }

    public function test_deletes_variant_image_and_falls_back_to_parent(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        [$productId, $variantId] = $this->createVariableProduct($token);

        $this->withToken($token)->post("/api/v1/products/{$productId}/image", [
            'image' => UploadedFile::fake()->image('parent.jpg'),
        ])->assertOk();

        $this->withToken($token)->post("/api/v1/products/{$productId}/variants/{$variantId}/image", [
            'image' => UploadedFile::fake()->image('variant.jpg'),
        ])->assertOk();

        $path = ProductVariant::find($variantId)->image;

        $response = $this->withToken($token)->deleteJson("/api/v1/products/{$productId}/variants/{$variantId}/image");

        $response->assertOk();
        $row = collect($response->json('data.variants'))->firstWhere('id', $variantId);
        $this->assertNull($row['own_image']);
        $this->assertStringContainsString('/storage/products/', $row['image']);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_returns_404_when_variant_belongs_to_another_product(): void
    {
        Storage::fake('public');
        $token = $this->registerOwner();
        [$productA, $variantA] = $this->createVariableProduct($token, 'Product A');
        [$productB] = $this->createVariableProduct($token, 'Product B');

        $this->withToken($token)->post("/api/v1/products/{$productB}/variants/{$variantA}/image", [
            'image' => UploadedFile::fake()->image('hack.jpg'),
        ])->assertNotFound();
    }

    public function test_returns_404_when_variant_image_upload_for_another_store(): void
    {
        Storage::fake('public');
        $ownerA = $this->registerOwner('variant-a@test.com');
        [$productId, $variantId] = $this->createVariableProduct($ownerA);
        $ownerB = $this->registerOwner('variant-b@test.com');

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($ownerB)->post("/api/v1/products/{$productId}/variants/{$variantId}/image", [
            'image' => UploadedFile::fake()->image('hack.jpg'),
        ])->assertNotFound();

        $this->assertNull(ProductVariant::withoutGlobalScopes()->find($variantId)?->image);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function createVariableProduct(string $token, string $name = 'Shirt'): array
    {
        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => $name,
            'type' => 'variable',
            'selling_price' => 0,
            'variants' => [
                ['name' => 'Red', 'selling_price' => 100, 'stock_quantity' => 5],
                ['name' => 'Blue', 'selling_price' => 110, 'stock_quantity' => 3],
            ],
        ]);

        $response->assertCreated();
        $productId = (int) $response->json('data.id');
        $variantId = (int) $response->json('data.variants.0.id');

        return [$productId, $variantId];
    }

    protected function registerOwner(string $email = 'variant-photo@test.com'): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Variant Photo Store',
            'owner_name' => 'Owner',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
