<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson('/api/v1/products/1/adjust-stock', ['quantity' => 1])
            ->assertUnauthorized();
    }

    public function test_rejects_zero_quantity_with_422(): void
    {
        $token = $this->registerOwner();
        $productId = $this->createProduct($token, 5);

        $this->withToken($token)
            ->postJson("/api/v1/products/{$productId}/adjust-stock", [
                'quantity' => 0,
                'reason' => 'noop',
            ])
            ->assertUnprocessable();
    }

    public function test_valid_adjustment_updates_stock_and_writes_a_movement(): void
    {
        $token = $this->registerOwner();
        $productId = $this->createProduct($token, 5);

        $response = $this->withToken($token)
            ->postJson("/api/v1/products/{$productId}/adjust-stock", [
                'quantity' => 10,
                'reason' => 'opening stock',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.stock_quantity', 15);

        $this->assertSame(15.0, (float) Product::find($productId)->stock_quantity);
        $this->assertSame(1, StockMovement::where('product_id', $productId)->where('type', 'adjustment')->count());
        $this->assertSame(10.0, (float) StockMovement::where('product_id', $productId)->value('quantity'));
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Test Store',
            'owner_name' => 'Owner',
            'email' => 'owner@test.com',
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
}
