<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\StoreCapability;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_returns_403_when_returns_capability_is_disabled(): void
    {
        $token = $this->registerOwner();
        $productId = $this->createProduct($token, 10);
        $sale = $this->createSale($token, $productId, 2);

        $this->withToken($token)
            ->postJson('/api/v1/sale-returns', [
                'sale_id' => $sale['id'],
                'refund_method' => 'cash',
                'restock' => true,
                'items' => [
                    ['sale_item_id' => $sale['items'][0]['id'], 'quantity' => 1],
                ],
            ])
            ->assertForbidden();
    }

    public function test_valid_return_restocks_and_records_the_refund(): void
    {
        $token = $this->registerOwner();
        $productId = $this->createProduct($token, 10);
        $sale = $this->createSale($token, $productId, 2);

        StoreCapability::create([
            'store_id' => $sale['store_id'] ?? Product::find($productId)->store_id,
            'capability' => 'returns',
            'enabled' => true,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/sale-returns', [
                'sale_id' => $sale['id'],
                'refund_method' => 'cash',
                'restock' => true,
                'items' => [
                    ['sale_item_id' => $sale['items'][0]['id'], 'quantity' => 1],
                ],
            ]);

        $response->assertCreated();
        $this->assertSame(1, SaleReturn::count());
        $this->assertSame(9.0, (float) Product::find($productId)->stock_quantity);
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Return Store',
            'owner_name' => 'Owner',
            'email' => 'returns@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }

    protected function createProduct(string $token, float $stock): int
    {
        $response = $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Soap',
            'type' => 'simple',
            'selling_price' => 50,
            'cost_price' => 30,
            'track_stock' => true,
            'stock_quantity' => $stock,
            'alert_quantity' => 2,
        ]);

        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function createSale(string $token, int $productId, float $qty): array
    {
        $response = $this->withToken($token)->postJson('/api/v1/sales', [
            'paid' => 100,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $productId, 'quantity' => $qty, 'unit_price' => 50],
            ],
        ]);

        $response->assertCreated();

        return $response->json('data');
    }
}
