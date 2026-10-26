<?php

namespace Tests\Feature;

use App\Models\StoreSubscription;
use App\Models\SubscriptionPayment;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_sandbox_checkout_activates_plan_and_records_payment(): void
    {
        $token = $this->registerOwner();
        $proId = (int) $this->withToken($token)->getJson('/api/v1/billing/plans')
            ->json('data.2.id');

        $response = $this->withToken($token)->postJson('/api/v1/billing/checkout', [
            'subscription_plan_id' => $proId,
            'gateway' => 'sandbox',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'paid');

        $storeId = (int) $this->withToken($token)->getJson('/api/v1/auth/me')->json('data.store.id');

        $this->assertSame(1, SubscriptionPayment::query()->where('store_id', $storeId)->where('status', 'paid')->count());
        $this->assertSame(
            $proId,
            (int) StoreSubscription::query()->where('store_id', $storeId)->where('status', 'active')->value('subscription_plan_id')
        );
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Billing Store',
            'owner_name' => 'Owner',
            'email' => 'billing-owner@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
