<?php

namespace App\Services;

use App\Models\StoreCapability;
use App\Models\StoreSubscription;
use App\Support\Capabilities;
use App\Support\StoreContext;

/**
 * Resolves whether a store is allowed to use a given capability (feature).
 *
 * Effective access = per-store override (super admin) first,
 * otherwise the active subscription plan features,
 * otherwise the platform defaults.
 */
class CapabilityService
{
    public function allows(?int $storeId, string $capability): bool
    {
        if (! $storeId) {
            return false;
        }

        $effective = $this->effectiveFor($storeId);

        return in_array($capability, $effective, true);
    }

    /**
     * Full list of capability keys a store currently has.
     */
    public function effectiveFor(int $storeId): array
    {
        return StoreContext::withoutScope(function () use ($storeId) {
            $planFeatures = $this->activePlanFeatures($storeId);
            $effective = $planFeatures ?? Capabilities::DEFAULTS;

            $overrides = StoreCapability::where('store_id', $storeId)->get();
            foreach ($overrides as $override) {
                if ($override->enabled && ! in_array($override->capability, $effective, true)) {
                    $effective[] = $override->capability;
                } elseif (! $override->enabled) {
                    $effective = array_values(array_filter($effective, fn ($c) => $c !== $override->capability));
                }
            }

            return array_values($effective);
        });
    }

    /**
     * @return list<string>|null
     */
    protected function activePlanFeatures(int $storeId): ?array
    {
        $subscription = StoreSubscription::with('plan')
            ->where('store_id', $storeId)
            ->whereIn('status', ['active', 'trial'])
            ->latest('id')
            ->first();

        if ($subscription === null || $subscription->plan === null) {
            return null;
        }

        $features = $subscription->plan->features;

        return is_array($features) ? array_values($features) : [];
    }
}
