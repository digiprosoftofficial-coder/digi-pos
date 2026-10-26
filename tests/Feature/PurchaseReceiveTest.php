<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreCapability;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_received_purchase_increases_stock_and_updates_cost(): void
    {
        $token = $this->registerOwner();
        $storeId = (int) $this->withToken($token)->getJson('/api/v1/auth/me')->json('data.store.id');

        StoreCapability::query()->create([
            'store_id' => $storeId,
            'capability' => 'purchases',
            'enabled' => true,
        ]);

        $productId = (int) $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Rice',
            'type' => 'simple',
            'cost_price' => 40,
            'selling_price' => 55,
            'track_stock' => true,
            'stock_quantity' => 5,
        ])->json('data.id');

        $this->withToken($token)->postJson('/api/v1/purchases', [
            'status' => 'received',
            'update_cost' => true,
            'paid' => 200,
            'items' => [
                ['product_id' => $productId, 'quantity' => 4, 'unit_cost' => 45],
            ],
        ])->assertCreated();

        $product = Product::find($productId);
        $this->assertSame(9.0, (float) $product->stock_quantity);
        $this->assertSame(45.0, (float) $product->cost_price);
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Purchase Store',
            'owner_name' => 'Owner',
            'email' => 'purchase@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
