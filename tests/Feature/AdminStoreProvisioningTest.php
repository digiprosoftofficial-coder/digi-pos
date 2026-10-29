<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\StoreContext;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminStoreProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->postJson('/api/v1/admin/stores', [])->assertUnauthorized();
    }

    public function test_returns_403_when_store_owner_creates_a_store(): void
    {
        $token = $this->registerOwner();

        $this->withToken($token)
            ->postJson('/api/v1/admin/stores', $this->payload())
            ->assertForbidden();
    }

    public function test_returns_422_when_required_fields_are_missing(): void
    {
        $this->withToken($this->superAdminToken())
            ->postJson('/api/v1/admin/stores', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_name', 'owner_name', 'email', 'password', 'start_mode']);
    }

    public function test_creates_a_trial_store_with_custom_days_and_verified_owner(): void
    {
        $planId = (int) SubscriptionPlan::query()->where('slug', 'basic')->value('id');

        $response = $this->withToken($this->superAdminToken())
            ->postJson('/api/v1/admin/stores', $this->payload([
                'start_mode' => 'trial',
                'trial_days' => 5,
                'subscription_plan_id' => $planId,
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.store.status', 'trial')
            ->assertJsonPath('data.owner.email', 'new-store@test.com')
            ->assertJsonPath('data.owner.email_verified', true);

        $store = Store::query()->where('email', 'new-store@test.com')->firstOrFail();
        $owner = User::query()->where('email', 'new-store@test.com')->firstOrFail();

        $this->assertSame('trial', $store->status);
        $this->assertEqualsWithDelta(now()->addDays(5)->getTimestamp(), $store->trial_ends_at->getTimestamp(), 2);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertDatabaseHas('store_subscriptions', [
            'store_id' => $store->id,
            'subscription_plan_id' => $planId,
            'status' => 'trial',
        ]);
    }

    public function test_creates_an_active_store_without_a_trial(): void
    {
        $planId = (int) SubscriptionPlan::query()->where('slug', 'pro')->value('id');

        $this->withToken($this->superAdminToken())
            ->postJson('/api/v1/admin/stores', $this->payload([
                'email' => 'active-store@test.com',
                'start_mode' => 'active',
                'subscription_plan_id' => $planId,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.store.status', 'active');

        $store = Store::query()->where('email', 'active-store@test.com')->firstOrFail();

        $this->assertNull($store->trial_ends_at);
        $this->assertDatabaseHas('store_subscriptions', [
            'store_id' => $store->id,
            'subscription_plan_id' => $planId,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'New Mart',
            'owner_name' => 'Owner',
            'email' => 'new-store@test.com',
            'phone' => '01700000000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'start_mode' => 'trial',
        ], $overrides);
    }

    protected function superAdminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'provision-admin@test.com',
            'password' => 'password',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        return $admin->createToken('test')->plainTextToken;
    }

    protected function registerOwner(): string
    {
        Auth::forgetGuards();
        StoreContext::clear();

        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Owner Store',
            'owner_name' => 'Owner',
            'email' => 'provision-owner@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
