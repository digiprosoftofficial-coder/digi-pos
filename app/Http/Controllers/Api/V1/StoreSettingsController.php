<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettingsController extends Controller
{
    use ApiResponses;

    public function show(): JsonResponse
    {
        $store = Store::findOrFail(StoreContext::id());

        return $this->ok(new StoreResource($store));
    }

    public function update(Request $request): JsonResponse
    {
        $store = Store::findOrFail(StoreContext::id());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'max:8'],
            'currency_symbol' => ['nullable', 'string', 'max:8'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'locale' => ['nullable', 'in:bn,en'],
            'receipt_header' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'loyalty' => ['nullable', 'array'],
            'loyalty.enabled' => ['boolean'],
            'loyalty.earn_per_unit' => ['nullable', 'numeric', 'min:0'],
            'loyalty.redeem_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Loyalty config lives in the settings JSON blob.
        if (array_key_exists('loyalty', $data)) {
            $settings = $store->settings ?? [];
            $settings['loyalty'] = [
                'enabled' => (bool) ($data['loyalty']['enabled'] ?? false),
                'earn_per_unit' => (float) ($data['loyalty']['earn_per_unit'] ?? 0),
                'redeem_value' => (float) ($data['loyalty']['redeem_value'] ?? 0),
            ];
            $store->settings = $settings;
            unset($data['loyalty']);
        }

        $store->update($data);

        return $this->ok(new StoreResource($store->fresh()), 'Settings updated.');
    }
}
