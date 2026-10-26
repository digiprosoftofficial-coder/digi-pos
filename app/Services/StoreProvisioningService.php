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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StoreProvisioningService
{
    /**
     * Create a new store (tenant) together with its owner user.
     *
     * @return array{store: Store, user: User}
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $store = Store::create([
                'name' => $data['store_name'],
                'slug' => $this->uniqueSlug($data['store_name']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'currency' => $data['currency'] ?? 'BDT',
                'currency_symbol' => $data['currency_symbol'] ?? '৳',
                'locale' => $data['locale'] ?? 'bn',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::create([
                'store_id' => $store->id,
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $user->assignRole(Permissions::ROLE_OWNER);

            Branch::create([
                'store_id' => $store->id,
                'name' => 'Main Branch',
                'phone' => $data['phone'] ?? null,
                'is_main' => true,
                'is_active' => true,
            ]);

            // A couple of sensible default units to get started.
            foreach ([['Piece', 'pc'], ['Kilogram', 'kg'], ['Box', 'box']] as [$name, $short]) {
                Unit::create([
                    'store_id' => $store->id,
                    'name' => $name,
                    'short_name' => $short,
                ]);
            }

            $freePlan = SubscriptionPlan::query()->where('slug', 'free')->where('is_active', true)->first();
            if ($freePlan) {
                StoreSubscription::query()->create([
                    'store_id' => $store->id,
                    'subscription_plan_id' => $freePlan->id,
                    'status' => 'trial',
                    'starts_at' => now(),
                    'ends_at' => $store->trial_ends_at,
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

        while (Store::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
