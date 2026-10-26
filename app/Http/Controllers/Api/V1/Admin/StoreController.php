<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Models\StoreCapability;
use App\Models\StoreSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CapabilityService;
use App\Services\SubscriptionAssignmentService;
use App\Support\Capabilities;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected CapabilityService $capabilities,
        protected SubscriptionAssignmentService $subscriptionAssignment,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $stores = Store::query()
            ->with(['currentSubscription.plan'])
            ->withCount(['users', 'products', 'sales'])
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->ok($stores);
    }

    public function show(Store $store): JsonResponse
    {
        $store->loadCount(['users', 'products', 'sales']);
        $owner = User::where('store_id', $store->id)->role(Permissions::ROLE_OWNER)->first();
        $subscription = StoreSubscription::with('plan')->where('store_id', $store->id)->latest('id')->first();

        return $this->ok([
            'store' => $store,
            'owner' => $owner,
            'subscription' => $subscription,
            'revenue' => (float) $store->sales()->where('status', 'completed')->sum('total'),
        ]);
    }

    public function updateStatus(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended,trial'],
        ]);

        $store->update(['status' => $data['status']]);

        return $this->ok($store, 'Store status updated.');
    }

    public function destroy(Store $store): JsonResponse
    {
        $store->delete();

        return $this->ok(null, 'Store removed.');
    }

    /**
     * Get all capabilities with their effective state for this store.
     */
    public function capabilities(Store $store): JsonResponse
    {
        $effective = $this->capabilities->effectiveFor($store->id);
        $overrides = StoreCapability::where('store_id', $store->id)->pluck('enabled', 'capability');

        $list = collect(Capabilities::ALL)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'enabled' => in_array($key, $effective, true),
            'is_override' => $overrides->has($key),
            'is_default' => in_array($key, Capabilities::DEFAULTS, true),
        ])->values();

        return $this->ok($list);
    }

    /**
     * Set per-store capability overrides.
     * Expects: capabilities => [ {key, enabled}, ... ]
     */
    public function setCapabilities(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'capabilities' => ['required', 'array'],
            'capabilities.*.key' => ['required', 'string'],
            'capabilities.*.enabled' => ['required', 'boolean'],
        ]);

        foreach ($data['capabilities'] as $cap) {
            if (! Capabilities::exists($cap['key'])) {
                continue;
            }

            StoreCapability::updateOrCreate(
                ['store_id' => $store->id, 'capability' => $cap['key']],
                ['enabled' => $cap['enabled']]
            );
        }

        return $this->ok($this->capabilities->effectiveFor($store->id), 'Capabilities updated.');
    }

    public function assignPlan(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'status' => ['nullable', 'in:active,expired,cancelled,trial'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
        $status = $data['status'] ?? 'active';

        $subscription = $this->subscriptionAssignment->assign($store, $plan, $status);

        if (! empty($data['ends_at'])) {
            $subscription->update(['ends_at' => $data['ends_at']]);
        }

        return $this->ok($subscription->load('plan'), 'Plan assigned.');
    }

    /**
     * Issue an access token to log in as the store's owner (impersonation).
     */
    public function impersonate(Store $store): JsonResponse
    {
        $owner = User::where('store_id', $store->id)->role(Permissions::ROLE_OWNER)->first();

        if (! $owner) {
            return $this->error('This store has no owner to impersonate.', 404);
        }

        $token = $owner->createToken('impersonation')->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user' => new UserResource($owner->load('store')),
        ], 'Impersonation token issued.');
    }
}
