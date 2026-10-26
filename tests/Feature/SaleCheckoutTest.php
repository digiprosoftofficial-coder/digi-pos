<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_completed_sale_reduces_stock_and_records_payment(): void
    {
        $token = $this->registerOwner();
        $productId = (int) $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
            'cost_price' => 30,
            'track_stock' => true,
            'stock_quantity' => 10,
        ])->json('data.id');

        $response = $this->withToken($token)->postJson('/api/v1/sales', [
            'paid' => 100,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total', 100)
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertSame(8.0, (float) Product::find($productId)->stock_quantity);
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Sale Store',
            'owner_name' => 'Owner',
            'email' => 'sale-checkout@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
