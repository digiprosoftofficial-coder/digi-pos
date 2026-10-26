<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Support\Capabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionPlanController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        return $this->ok(SubscriptionPlan::orderBy('price')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);

        return $this->created(SubscriptionPlan::create($data));
    }

    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $plan->update($this->validateData($request));

        return $this->ok($plan);
    }

    public function destroy(SubscriptionPlan $plan): JsonResponse
    {
        $plan->delete();

        return $this->ok(null, 'Plan removed.');
    }

    /** List of all capability keys/labels for building plans. */
    public function capabilities(): JsonResponse
    {
        $list = collect(Capabilities::ALL)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values();

        return $this->ok($list);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', 'in:monthly,yearly,lifetime'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_products' => ['nullable', 'integer', 'min:1'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'features' => ['array'],
            'features.*' => ['string'],
            'is_active' => ['boolean'],
        ]);
    }
}
