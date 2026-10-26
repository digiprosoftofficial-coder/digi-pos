<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Support\Capabilities;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'billing_period' => 'monthly',
                'max_users' => 2,
                'max_products' => 50,
                'max_branches' => 1,
                'features' => ['pos', 'products', 'variants', 'customers', 'reports.basic', 'staff'],
                'is_active' => true,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 500,
                'billing_period' => 'monthly',
                'max_users' => 5,
                'max_products' => 1000,
                'max_branches' => 1,
                'features' => ['pos', 'products', 'variants', 'customers', 'reports.basic', 'staff', 'purchases', 'returns', 'barcode_print'],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 1500,
                'billing_period' => 'monthly',
                'max_users' => null,
                'max_products' => null,
                'max_branches' => 10,
                'features' => Capabilities::keys(),
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
