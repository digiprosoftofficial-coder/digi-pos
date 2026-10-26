<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creates_variable_product_with_variant_stock(): void
    {
        $token = $this->registerOwner();

        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Clamon',
            'type' => 'variable',
            'track_stock' => true,
            'variants' => [
                ['name' => '500ml', 'selling_price' => 50, 'stock_quantity' => 20],
                ['name' => '1000ml', 'selling_price' => 90, 'stock_quantity' => 20],
                ['name' => '2L', 'selling_price' => 120, 'stock_quantity' => 20],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.variants.0.stock_quantity', 20)
            ->assertJsonPath('data.variants.1.stock_quantity', 20)
            ->assertJsonPath('data.variants.2.stock_quantity', 20);

        $this->assertSame(3, ProductVariant::count());
        $this->assertSame(60.0, (float) ProductVariant::sum('stock_quantity'));
    }

    public function test_updates_variant_stock_and_prices(): void
    {
        $token = $this->registerOwner();
        $created = $this->createVariableProduct($token);
        $firstId = (int) $created['variants'][0]['id'];
        $secondId = (int) $created['variants'][1]['id'];

        $response = $this->withToken($token)->putJson("/api/v1/products/{$created['id']}", [
            'name' => 'Clamon',
            'type' => 'variable',
            'track_stock' => true,
            'variants' => [
                ['id' => $firstId, 'name' => '500ml', 'selling_price' => 55, 'stock_quantity' => 18],
                ['id' => $secondId, 'name' => '1000ml', 'selling_price' => 95, 'stock_quantity' => 12],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.variants.0.selling_price', 55)
            ->assertJsonPath('data.variants.0.stock_quantity', 18)
            ->assertJsonPath('data.variants.1.stock_quantity', 12);

        $this->assertSame(2, ProductVariant::where('product_id', $created['id'])->count());
        $this->assertSame(18.0, (float) ProductVariant::find($firstId)->stock_quantity);
        $this->assertNull(ProductVariant::find($created['variants'][2]['id']));
    }

    public function test_keeps_unique_variant_skus_when_sku_is_blank_on_update(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $token = $this->registerOwner();
        $created = $this->createVariableProduct($token);

        $response = $this->withToken($token)->putJson("/api/v1/products/{$created['id']}", [
            'name' => 'Clamon',
            'type' => 'variable',
            'variants' => [
                ['id' => $created['variants'][0]['id'], 'name' => '500ml', 'sku' => '', 'selling_price' => 50, 'stock_quantity' => 20],
                ['id' => $created['variants'][1]['id'], 'name' => '1000ml', 'sku' => '', 'selling_price' => 90, 'stock_quantity' => 20],
                ['id' => $created['variants'][2]['id'], 'name' => '2L', 'sku' => '', 'selling_price' => 120, 'stock_quantity' => 20],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.variants.0.sku', 'P-20261021-0001-01')
            ->assertJsonPath('data.variants.1.sku', 'P-20261021-0001-02')
            ->assertJsonPath('data.variants.2.sku', 'P-20261021-0001-03');
    }

    public function test_repairs_placeholder_00_variant_skus_on_update(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $token = $this->registerOwner();
        $created = $this->createVariableProduct($token);
        $parentSku = $created['sku'];

        foreach ($created['variants'] as $variant) {
            ProductVariant::where('id', $variant['id'])->update(['sku' => $parentSku.'-00']);
        }

        $response = $this->withToken($token)->putJson("/api/v1/products/{$created['id']}", [
            'name' => 'Clamon',
            'type' => 'variable',
            'variants' => [
                ['id' => $created['variants'][0]['id'], 'name' => '500ml', 'sku' => '', 'selling_price' => 50, 'stock_quantity' => 20],
                ['id' => $created['variants'][1]['id'], 'name' => '1000ml', 'sku' => '', 'selling_price' => 90, 'stock_quantity' => 20],
                ['id' => $created['variants'][2]['id'], 'name' => '2L', 'sku' => '', 'selling_price' => 120, 'stock_quantity' => 20],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.variants.0.sku', $parentSku.'-01')
            ->assertJsonPath('data.variants.1.sku', $parentSku.'-02')
            ->assertJsonPath('data.variants.2.sku', $parentSku.'-03');
    }

    public function test_adds_a_variant_on_update(): void
    {
        $token = $this->registerOwner();
        $created = $this->createVariableProduct($token);

        $response = $this->withToken($token)->putJson("/api/v1/products/{$created['id']}", [
            'name' => 'Clamon',
            'type' => 'variable',
            'variants' => [
                ...$created['variants'],
                ['name' => '250ml', 'selling_price' => 30, 'stock_quantity' => 8],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(4, ProductVariant::where('product_id', $created['id'])->count());
        $this->assertSame(8.0, (float) ProductVariant::where('name', '250ml')->value('stock_quantity'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function createVariableProduct(string $token): array
    {
        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Clamon',
            'type' => 'variable',
            'track_stock' => true,
            'variants' => [
                ['name' => '500ml', 'selling_price' => 50, 'stock_quantity' => 20],
                ['name' => '1000ml', 'selling_price' => 90, 'stock_quantity' => 20],
                ['name' => '2L', 'selling_price' => 120, 'stock_quantity' => 20],
            ],
        ]);

        $response->assertCreated();

        return $response->json('data');
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Variant Store',
            'owner_name' => 'Owner',
            'email' => 'variants@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
