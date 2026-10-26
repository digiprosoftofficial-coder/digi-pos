<?php

namespace Tests\Feature;

use App\Support\StoreContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_store_b_cannot_update_store_a_product(): void
    {
        $tokenA = $this->registerOwner('tenant-a@test.com');
        $productId = (int) $this->withToken($tokenA)->postJson('/api/v1/products', [
            'name' => 'Secret Soap',
            'type' => 'simple',
            'selling_price' => 50,
        ])->json('data.id');

        $tokenB = $this->registerOwner('tenant-b@test.com');
        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($tokenB)->putJson("/api/v1/products/{$productId}", [
            'name' => 'Hacked',
            'type' => 'simple',
            'selling_price' => 1,
        ])->assertNotFound();
    }

    public function test_store_b_cannot_read_store_a_sale(): void
    {
        $tokenA = $this->registerOwner('sale-a@test.com');
        $productId = (int) $this->withToken($tokenA)->postJson('/api/v1/products', [
            'name' => 'Item',
            'type' => 'simple',
            'selling_price' => 40,
            'track_stock' => false,
        ])->json('data.id');

        $saleId = (int) $this->withToken($tokenA)->postJson('/api/v1/sales', [
            'paid' => 40,
            'payment_method' => 'cash',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])->json('data.id');

        $tokenB = $this->registerOwner('sale-b@test.com');
        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($tokenB)->getJson("/api/v1/sales/{$saleId}")->assertNotFound();
    }

    protected function registerOwner(string $email): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Store '.$email,
            'owner_name' => 'Owner',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
