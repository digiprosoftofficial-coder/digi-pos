<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StoreSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Validation\ValidationException;

class PlanLimitService
{
    public function effectivePlan(int $storeId): ?SubscriptionPlan
    {
        return StoreContext::withoutScope(function () use ($storeId) {
            $subscription = StoreSubscription::query()
                ->with('plan')
                ->where('store_id', $storeId)
                ->whereIn('status', ['active', 'trial'])
                ->latest('id')
                ->first();

            if ($subscription?->plan) {
                return $subscription->plan;
            }

            return SubscriptionPlan::query()->where('slug', 'free')->where('is_active', true)->first();
        });
    }

    public function assertCanAddProduct(int $storeId): void
    {
        $plan = $this->effectivePlan($storeId);
        $max = $plan?->max_products;

        if ($max === null) {
            return;
        }

        $count = Product::query()->where('store_id', $storeId)->count();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'name' => ["Your {$plan->name} plan allows up to {$max} products. Upgrade to add more."],
            ]);
        }
    }

    public function assertCanAddUser(int $storeId): void
    {
        $plan = $this->effectivePlan($storeId);
        $max = $plan?->max_users;

        if ($max === null) {
            return;
        }

        $count = User::query()->where('store_id', $storeId)->count();

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'email' => ["Your {$plan->name} plan allows up to {$max} users. Upgrade to add more."],
            ]);
        }
    }
}
