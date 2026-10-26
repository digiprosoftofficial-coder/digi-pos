<?php

namespace Tests\Feature;

use App\Models\StoreCapability;
use App\Models\SubscriptionPlan;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_blocks_product_create_when_free_plan_limit_reached(): void
    {
        $token = $this->registerOwner();
        $free = SubscriptionPlan::query()->where('slug', 'free')->firstOrFail();
        $free->update(['max_products' => 1]);

        $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'First',
            'type' => 'simple',
            'selling_price' => 10,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Second',
            'type' => 'simple',
            'selling_price' => 20,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_blocks_staff_create_when_user_limit_reached(): void
    {
        $token = $this->registerOwner();
        $free = SubscriptionPlan::query()->where('slug', 'free')->firstOrFail();
        $free->update(['max_users' => 1]);

        StoreCapability::query()->create([
            'store_id' => $this->storeIdFromToken($token),
            'capability' => 'staff',
            'enabled' => true,
        ]);

        $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'cashier',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    protected function registerOwner(string $email = 'limits@test.com'): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Limit Store',
            'owner_name' => 'Owner',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }

    protected function storeIdFromToken(string $token): int
    {
        $me = $this->withToken($token)->getJson('/api/v1/auth/me');

        return (int) $me->json('data.store.id');
    }
}
