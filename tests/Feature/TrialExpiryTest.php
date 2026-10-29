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

class TrialExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_blocks_pos_with_403_after_the_trial_ends_and_keeps_billing_open(): void
    {
        $token = $this->registerOwner();
        $store = Store::query()->where('email', 'expiry-owner@test.com')->firstOrFail();

        $this->assertSame('trial', $store->status);

        $this->travel(8)->days();

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($token)
            ->postJson('/api/v1/products', [
                'name' => 'Blocked',
                'type' => 'simple',
                'selling_price' => 10,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'trial_expired');

        $this->assertSame('expired', $store->fresh()->status);

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($token)
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.store.status', 'expired');
    }

    public function test_assigning_a_plan_reactivates_an_expired_store(): void
    {
        $ownerToken = $this->registerOwner();
        $store = Store::query()->where('email', 'expiry-owner@test.com')->firstOrFail();
        $store->update([
            'status' => 'expired',
            'trial_ends_at' => now()->subDay(),
        ]);

        $planId = (int) SubscriptionPlan::query()->where('slug', 'pro')->value('id');
        $adminToken = $this->superAdminToken();

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($adminToken)
            ->postJson("/api/v1/admin/stores/{$store->id}/plan", [
                'subscription_plan_id' => $planId,
            ])
            ->assertOk();

        $this->assertSame('active', $store->fresh()->status);
        $this->assertNull($store->fresh()->trial_ends_at);

        Auth::forgetGuards();
        StoreContext::clear();

        $this->withToken($ownerToken)
            ->postJson('/api/v1/products', [
                'name' => 'Allowed',
                'type' => 'simple',
                'selling_price' => 10,
            ])
            ->assertCreated();
    }

    protected function superAdminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'expiry-admin@test.com',
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
            'store_name' => 'Expiry Store',
            'owner_name' => 'Owner',
            'email' => 'expiry-owner@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
