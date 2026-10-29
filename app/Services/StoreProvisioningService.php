<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreProvisioningService
{
    public function __construct(protected PlatformSettingService $platformSettings) {}

    /**
     * Create a new store (tenant) together with its owner user.
     *
     * @param  array<string, mixed>  $data
     * @param  array{start_active?: bool, trial_days?: int|null, mark_email_verified?: bool, subscription_plan_id?: int|null}  $options
     * @return array{store: Store, user: User}
     */
    public function provision(array $data, array $options = []): array
    {
        $startActive = (bool) ($options['start_active'] ?? false);
        $markEmailVerified = (bool) ($options['mark_email_verified'] ?? false);
        $trialDays = array_key_exists('trial_days', $options) && $options['trial_days'] !== null
            ? $this->platformSettings->clampTrialDays((int) $options['trial_days'])
            : $this->platformSettings->trialDays();

        return DB::transaction(function () use ($data, $startActive, $markEmailVerified, $trialDays, $options) {
            $trialEndsAt = $startActive ? null : now()->addDays($trialDays);

            $store = Store::query()->create([
                'name' => $data['store_name'],
                'slug' => $this->uniqueSlug($data['store_name']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'currency' => $data['currency'] ?? 'BDT',
                'currency_symbol' => $data['currency_symbol'] ?? '৳',
                'locale' => $data['locale'] ?? 'bn',
                'status' => $startActive ? 'active' : 'trial',
                'trial_ends_at' => $trialEndsAt,
            ]);

            $user = User::query()->create([
                'store_id' => $store->id,
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => $markEmailVerified ? now() : null,
            ]);

            $user->assignRole(Permissions::ROLE_OWNER);

            Branch::query()->create([
                'store_id' => $store->id,
                'name' => 'Main Branch',
                'phone' => $data['phone'] ?? null,
                'is_main' => true,
                'is_active' => true,
            ]);

            foreach ([['Piece', 'pc'], ['Kilogram', 'kg'], ['Box', 'box']] as [$name, $short]) {
                Unit::query()->create([
                    'store_id' => $store->id,
                    'name' => $name,
                    'short_name' => $short,
                ]);
            }

            $plan = null;
            if (! empty($options['subscription_plan_id'])) {
                $plan = SubscriptionPlan::query()->find($options['subscription_plan_id']);
            }
            if (! $plan) {
                $plan = SubscriptionPlan::query()->where('slug', 'free')->where('is_active', true)->first();
            }

            if ($plan) {
                StoreSubscription::query()->create([
                    'store_id' => $store->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => $startActive ? 'active' : 'trial',
                    'starts_at' => now(),
                    'ends_at' => $trialEndsAt,
                ]);
            }

            return ['store' => $store, 'user' => $user];
        });
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $i = 1;

        while (Store::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
