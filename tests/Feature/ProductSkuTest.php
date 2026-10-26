<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson('/api/v1/products', [
            'name' => 'Soap',
            'type' => 'simple',
        ])->assertUnauthorized();
    }

    public function test_creates_product_with_date_based_sku_when_sku_is_blank(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $token = $this->registerOwner();

        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
            'cost_price' => 30,
            'track_stock' => true,
            'stock_quantity' => 5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'P-20261021-0001');

        $this->assertSame('P-20261021-0001', Product::find($response->json('data.id'))->sku);
    }

    public function test_keeps_provided_sku_on_create(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $token = $this->registerOwner();

        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Soap',
            'type' => 'simple',
            'sku' => 'COKE-500',
            'selling_price' => 50,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'COKE-500');

        $this->assertSame('COKE-500', Product::find($response->json('data.id'))->sku);
    }

    public function test_increments_daily_serial_for_the_same_store(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $token = $this->registerOwner();

        $first = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
        ]);
        $second = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Shampoo',
            'type' => 'simple',
            'selling_price' => 80,
        ]);

        $first->assertCreated()->assertJsonPath('data.sku', 'P-20261021-0001');
        $second->assertCreated()->assertJsonPath('data.sku', 'P-20261021-0002');
    }

    public function test_assigns_variant_skus_from_the_parent(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $token = $this->registerOwner();

        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Shirt',
            'type' => 'variable',
            'selling_price' => 0,
            'variants' => [
                ['name' => 'Red / M', 'selling_price' => 400, 'stock_quantity' => 3],
                ['name' => 'Blue / L', 'selling_price' => 420, 'stock_quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'P-20261021-0001')
            ->assertJsonPath('data.variants.0.sku', 'P-20261021-0001-01')
            ->assertJsonPath('data.variants.1.sku', 'P-20261021-0001-02');

        $this->assertSame(
            ['P-20261021-0001-01', 'P-20261021-0001-02'],
            ProductVariant::where('product_id', $response->json('data.id'))->orderBy('id')->pluck('sku')->all()
        );
    }

    public function test_does_not_replace_existing_sku_on_update(): void
    {
        $token = $this->registerOwner();
        $productId = $this->createProduct($token, ['sku' => 'COKE-500']);

        $response = $this->withToken($token)->putJson("/api/v1/products/{$productId}", [
            'name' => 'Coca Cola 500ml',
            'type' => 'simple',
            'sku' => '',
            'selling_price' => 55,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sku', 'COKE-500');

        $this->assertSame('COKE-500', Product::find($productId)->sku);
    }

    public function test_generates_sku_on_update_when_product_sku_is_empty(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $token = $this->registerOwner();
        $productId = $this->createProduct($token, ['sku' => 'TEMP']);

        Product::find($productId)->update(['sku' => null]);

        $response = $this->withToken($token)->putJson("/api/v1/products/{$productId}", [
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sku', 'P-20261021-0001');

        $this->assertSame('P-20261021-0001', Product::find($productId)->sku);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createProduct(string $token, array $overrides = []): int
    {
        $response = $this->withToken($token)->postJson('/api/v1/products', array_merge([
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
            'cost_price' => 30,
            'track_stock' => true,
            'stock_quantity' => 5,
        ], $overrides));

        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'SKU Store',
            'owner_name' => 'Owner',
            'email' => 'sku@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
