<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPosPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_simple_product_requires_selling_price_for_pos(): void
    {
        $token = $this->registerOwner();

        $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'No sell price',
            'type' => 'simple',
            'cost_price' => 30,
            'selling_price' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['selling_price']);
    }

    public function test_lookup_and_sale_use_selling_price_not_cost(): void
    {
        $token = $this->registerOwner();

        $create = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Margin item',
            'type' => 'simple',
            'cost_price' => 30,
            'selling_price' => 50,
            'track_stock' => true,
            'stock_quantity' => 5,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.cost_price', 30)
            ->assertJsonPath('data.selling_price', 50);

        $productId = (int) $create->json('data.id');

        $this->withToken($token)->getJson('/api/v1/lookup/search?q=Margin')
            ->assertOk()
            ->assertJsonPath('data.0.selling_price', 50)
            ->assertJsonPath('data.0.cost_price', 30);

        $sale = $this->withToken($token)->postJson('/api/v1/sales', [
            'paid' => 50,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ]);

        $sale->assertCreated()
            ->assertJsonPath('data.total', 50);

        $this->assertSame(50.0, (float) $sale->json('data.items.0.unit_price'));
        $this->assertSame(30.0, (float) $sale->json('data.items.0.unit_cost'));
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Pricing Store',
            'owner_name' => 'Owner',
            'email' => 'pos-pricing@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
