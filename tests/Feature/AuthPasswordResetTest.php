<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_forgot_password_returns_success_without_leaking_existence(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Reset Store',
            'owner_name' => 'Owner',
            'email' => 'reset-owner@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset-owner@test.com',
        ])->assertOk();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@test.com',
        ])->assertOk();
    }

    public function test_reset_password_updates_hash_and_invalidates_tokens(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'store_name' => 'Reset Store',
            'owner_name' => 'Owner',
            'email' => 'reset2@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();

        $user = User::query()->where('email', 'reset2@test.com')->firstOrFail();
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset2@test.com',
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }
}
