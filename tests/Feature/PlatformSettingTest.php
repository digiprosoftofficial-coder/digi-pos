<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PlatformSettingTest extends TestCase
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
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();
    }

    public function test_returns_403_when_store_owner_reads_platform_settings(): void
    {
        $token = $this->registerOwner();

        $this->withToken($token)->getJson('/api/v1/admin/settings')->assertForbidden();
    }

    public function test_returns_default_trial_days_when_none_are_saved(): void
    {
        $this->withToken($this->superAdminToken())
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.trial_days', 7);
    }

    public function test_saves_trial_days_and_applies_them_to_new_registrations(): void
    {
        $adminToken = $this->superAdminToken();

        $this->withToken($adminToken)
            ->putJson('/api/v1/admin/settings', ['trial_days' => 3])
            ->assertOk()
            ->assertJsonPath('data.trial_days', 3);

        $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Trial Store',
            'owner_name' => 'Owner',
            'email' => 'trial-days@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();

        $store = Store::query()->where('email', 'trial-days@test.com')->firstOrFail();

        $this->assertSame('trial', $store->status);
        $this->assertEqualsWithDelta(now()->addDays(3)->getTimestamp(), $store->trial_ends_at->getTimestamp(), 2);
    }

    public function test_rejects_trial_days_outside_the_allowed_range(): void
    {
        $token = $this->superAdminToken();

        $this->withToken($token)
            ->putJson('/api/v1/admin/settings', ['trial_days' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trial_days');

        $this->withToken($token)
            ->putJson('/api/v1/admin/settings', ['trial_days' => 400])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trial_days');
    }

    protected function superAdminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'settings-admin@test.com',
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
            'store_name' => 'Settings Store',
            'owner_name' => 'Owner',
            'email' => 'settings-owner@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
