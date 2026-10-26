<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreCapability;
use App\Models\StoreSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\StoreContext;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminStorePlanTest extends TestCase
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
        $this->postJson('/api/v1/admin/stores/1/plan', [
            'subscription_plan_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_returns_403_when_store_owner_assigns_a_plan(): void
    {
        $ownerToken = $this->registerOwner();
        $storeId = $this->storeIdFromToken($ownerToken);
        $planId = SubscriptionPlan::query()->where('slug', 'pro')->value('id');

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($ownerToken)->postJson("/api/v1/admin/stores/{$storeId}/plan", [
            'subscription_plan_id' => $planId,
        ])->assertForbidden();
    }

    public function test_assigns_plan_and_unlocks_plan_features_for_the_store(): void
    {
        $ownerToken = $this->registerOwner();
        $storeId = $this->storeIdFromToken($ownerToken);
        $planId = (int) SubscriptionPlan::query()->where('slug', 'pro')->value('id');
        $adminToken = $this->superAdminToken();

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($ownerToken)->getJson('/api/v1/suppliers')->assertForbidden();

        Auth::forgetGuards();
        StoreContext::clear();

        $response = $this->withToken($adminToken)->postJson("/api/v1/admin/stores/{$storeId}/plan", [
            'subscription_plan_id' => $planId,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subscription_plan_id', $planId)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.slug', 'pro');

        $this->assertDatabaseHas('store_subscriptions', [
            'store_id' => $storeId,
            'subscription_plan_id' => $planId,
            'status' => 'active',
        ]);
        $this->assertSame('active', Store::query()->find($storeId)->status);

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($ownerToken)->getJson('/api/v1/suppliers')->assertOk();
    }

    public function test_replaces_previous_active_subscription_when_assigning_a_new_plan(): void
    {
        $ownerToken = $this->registerOwner();
        $storeId = $this->storeIdFromToken($ownerToken);
        $freeId = (int) SubscriptionPlan::query()->where('slug', 'free')->value('id');
        $proId = (int) SubscriptionPlan::query()->where('slug', 'pro')->value('id');
        $adminToken = $this->superAdminToken();

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($adminToken)->postJson("/api/v1/admin/stores/{$storeId}/plan", [
            'subscription_plan_id' => $freeId,
        ])->assertOk();

        $this->withToken($adminToken)->postJson("/api/v1/admin/stores/{$storeId}/plan", [
            'subscription_plan_id' => $proId,
        ])->assertOk();

        $this->assertSame(1, StoreSubscription::query()->where('store_id', $storeId)->where('status', 'active')->count());
        $this->assertSame(2, StoreSubscription::query()->where('store_id', $storeId)->where('status', 'cancelled')->count());
        $this->assertSame(
            $proId,
            (int) StoreSubscription::query()->where('store_id', $storeId)->where('status', 'active')->value('subscription_plan_id')
        );
    }

    public function test_clears_capability_overrides_so_assigned_plan_takes_effect(): void
    {
        $ownerToken = $this->registerOwner();
        $storeId = $this->storeIdFromToken($ownerToken);
        $proId = (int) SubscriptionPlan::query()->where('slug', 'pro')->value('id');
        $adminToken = $this->superAdminToken();

        StoreCapability::query()->create([
            'store_id' => $storeId,
            'capability' => 'purchases',
            'enabled' => false,
        ]);

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($adminToken)->postJson("/api/v1/admin/stores/{$storeId}/plan", [
            'subscription_plan_id' => $proId,
        ])->assertOk();

        $this->assertDatabaseMissing('store_capabilities', [
            'store_id' => $storeId,
            'capability' => 'purchases',
        ]);

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($ownerToken)->getJson('/api/v1/suppliers')->assertOk();
    }

    public function test_rejects_missing_plan_id_with_422(): void
    {
        $ownerToken = $this->registerOwner();
        $storeId = $this->storeIdFromToken($ownerToken);
        $adminToken = $this->superAdminToken();

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($adminToken)->postJson("/api/v1/admin/stores/{$storeId}/plan", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription_plan_id']);
    }

    public function test_store_index_includes_the_assigned_plan(): void
    {
        $ownerToken = $this->registerOwner();
        $storeId = $this->storeIdFromToken($ownerToken);
        $proId = (int) SubscriptionPlan::query()->where('slug', 'pro')->value('id');
        $adminToken = $this->superAdminToken();

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($adminToken)->postJson("/api/v1/admin/stores/{$storeId}/plan", [
            'subscription_plan_id' => $proId,
        ])->assertOk();

        $response = $this->withToken($adminToken)->getJson('/api/v1/admin/stores');

        $response->assertOk();
        $this->assertSame('pro', $response->json('data.data.0.current_subscription.plan.slug'));
        $this->assertSame($storeId, $response->json('data.data.0.id'));
    }

    protected function storeIdFromToken(string $token): int
    {
        Auth::forgetGuards();
        StoreContext::clear();

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');
        $response->assertOk();

        return (int) $response->json('data.store_id');
    }

    protected function superAdminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'plan-admin@test.com',
            'password' => 'password',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        return $admin->createToken('test')->plainTextToken;
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Plan Store',
            'owner_name' => 'Owner',
            'email' => 'plan-owner@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
