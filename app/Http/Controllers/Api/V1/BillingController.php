<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionBillingService;
use App\Support\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponses;

    public function __construct(protected SubscriptionBillingService $billing) {}

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get(['id', 'name', 'slug', 'price', 'billing_period', 'max_users', 'max_products', 'max_branches', 'features']);

        return $this->ok($plans);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'gateway' => ['required', 'in:sandbox,manual,sslcommerz'],
        ]);

        $store = Store::findOrFail(StoreContext::id());
        $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);

        $result = $this->billing->checkout($store, $plan, $data['gateway']);

        return $this->ok($result, $result['message'] ?? 'Checkout started.');
    }
}
