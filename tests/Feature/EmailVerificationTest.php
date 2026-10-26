<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.require_email_verification', true);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_unverified_user_cannot_create_products(): void
    {
        $token = $this->registerOwner();

        $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Blocked',
            'type' => 'simple',
            'selling_price' => 10,
        ])->assertForbidden()
            ->assertJsonPath('code', 'email_unverified');
    }

    public function test_signed_verify_link_marks_email_verified(): void
    {
        $token = $this->registerOwner();
        $user = User::query()->where('email', 'verify@test.com')->firstOrFail();

        $url = URL::temporarySignedRoute('api.verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->withToken($token)->postJson('/api/v1/products', [
            'name' => 'Allowed',
            'type' => 'simple',
            'selling_price' => 10,
        ])->assertCreated();
    }

    protected function registerOwner(): string
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Verify Store',
            'owner_name' => 'Owner',
            'email' => 'verify@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }
}
