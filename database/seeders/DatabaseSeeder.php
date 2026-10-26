<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(SubscriptionPlanSeeder::class);

        // Platform super admin (no store).
        User::firstOrCreate(
            ['email' => 'superadmin@digipro.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'is_active' => true,
            ]
        );
    }
}
