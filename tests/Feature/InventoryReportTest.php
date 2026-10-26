<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_inventory_report_values_variable_stock_from_variants(): void
    {
        $token = $this->registerOwner();

        $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Shirt',
            'type' => 'variable',
            'track_stock' => true,
            'cost_price' => 40,
            'selling_price' => 100,
            'alert_quantity' => 2,
            'variants' => [
                ['name' => 'Red', 'cost_price' => 0, 'selling_price' => 0, 'stock_quantity' => 5],
                ['name' => 'Blue', 'cost_price' => 50, 'selling_price' => 120, 'stock_quantity' => 2],
            ],
        ])->assertCreated();

        $response = $this->withToken($token)->getJson('/api/v1/reports/inventory');

        $response->assertOk();
        // Red: 5 * 40 cost, 5 * 100 retail; Blue: 2 * 50, 2 * 120
        $this->assertSame(300.0, (float) $response->json('data.valuation_cost'));
        $this->assertSame(740.0, (float) $response->json('data.valuation_retail'));

        $low = collect($response->json('data.low_stock'));
        $this->assertTrue($low->contains(fn (array $row) => $row['name'] === 'Shirt - Blue' && (float) $row['stock_quantity'] === 2.0));
    }

    public function test_sale_uses_parent_price_when_variant_selling_is_zero(): void
    {
        $token = $this->registerOwner();

        $product = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Shirt',
            'type' => 'variable',
            'track_stock' => false,
            'selling_price' => 99,
            'cost_price' => 30,
            'variants' => [
                ['name' => 'Red', 'selling_price' => 0, 'cost_price' => 0, 'stock_quantity' => 0],
            ],
        ])->assertCreated();

        $variantId = (int) $product->json('data.variants.0.id');
        $productId = (int) $product->json('data.id');

        $sale = $this->withToken($token)->postJson('/api/v1/sales', [
            'paid' => 99,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => 1],
            ],
        ]);

        $sale->assertCreated();
        $this->assertSame(99.0, (float) $sale->json('data.items.0.unit_price'));
        $this->assertSame(30.0, (float) $sale->json('data.items.0.unit_cost'));
    }

    protected function registerOwner(string $email = 'inventory-report@test.com'): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Inventory Report Store',
            'owner_name' => 'Owner',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
