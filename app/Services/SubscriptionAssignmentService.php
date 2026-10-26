<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreCapability;
use App\Models\StoreSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class SubscriptionAssignmentService
{
    public function assign(Store $store, SubscriptionPlan $plan, string $status = 'active'): StoreSubscription
    {
        return DB::transaction(function () use ($store, $plan, $status) {
            StoreSubscription::query()
                ->where('store_id', $store->id)
                ->whereIn('status', ['active', 'trial'])
                ->update(['status' => 'cancelled']);

            $subscription = StoreSubscription::query()->create([
                'store_id' => $store->id,
                'subscription_plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => now(),
                'ends_at' => null,
            ]);

            StoreCapability::query()->where('store_id', $store->id)->delete();

            if ($status === 'active' && $store->status === 'trial') {
                $store->update(['status' => 'active']);
            }

            return $subscription;
        });
    }
}
