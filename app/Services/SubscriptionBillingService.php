<?php

namespace App\Services;

use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Support\Permissions;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SubscriptionBillingService
{
    public function __construct(protected SubscriptionAssignmentService $assignment) {}

    /**
     * @return array<string, mixed>
     */
    public function checkout(Store $store, SubscriptionPlan $plan, string $gateway): array
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => ['This plan is not available.']]);
        }

        $amount = (float) $plan->price;

        if ($gateway === 'sandbox') {
            if (! config('billing.sandbox_checkout_enabled')) {
                throw ValidationException::withMessages(['gateway' => ['Sandbox checkout is disabled.']]);
            }

            $payment = $this->recordPayment($store, $plan, $gateway, $amount, 'paid');
            $subscription = $this->assignment->assign($store->fresh(), $plan, 'active');

            return [
                'status' => 'paid',
                'payment' => $payment,
                'subscription' => $subscription->load('plan'),
                'message' => 'Plan activated (sandbox).',
            ];
        }

        if ($gateway === 'manual') {
            $payment = $this->recordPayment($store, $plan, $gateway, $amount, 'pending');

            return [
                'status' => 'pending',
                'payment' => $payment,
                'message' => 'Payment recorded. Our team will activate your plan after confirmation.',
            ];
        }

        if ($gateway === 'sslcommerz') {
            return $this->sslCommerzCheckout($store, $plan, $amount);
        }

        throw ValidationException::withMessages(['gateway' => ['Unsupported payment gateway.']]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sslCommerzCheckout(Store $store, SubscriptionPlan $plan, float $amount): array
    {
        $storeId = config('services.sslcommerz.store_id');
        $storePassword = config('services.sslcommerz.store_password');
        $sandbox = (bool) config('services.sslcommerz.sandbox', true);

        if (! $storeId || ! $storePassword) {
            throw ValidationException::withMessages([
                'gateway' => ['SSLCommerz is not configured on this server.'],
            ]);
        }

        $payment = $this->recordPayment($store, $plan, 'sslcommerz', $amount, 'pending');
        $base = $sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';

        $owner = $store->users()->whereHas('roles', fn ($q) => $q->where('name', Permissions::ROLE_OWNER))->first();

        $response = Http::asForm()->post("{$base}/gwprocess/v4/api.php", [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => 'SUB-'.$payment->id.'-'.time(),
            'success_url' => url('/billing/success?payment='.$payment->id),
            'fail_url' => url('/billing/fail?payment='.$payment->id),
            'cancel_url' => url('/billing/cancel?payment='.$payment->id),
            'cus_name' => $owner?->name ?? $store->name,
            'cus_email' => $owner?->email ?? $store->email ?? 'billing@example.com',
            'cus_phone' => $store->phone ?? '01700000000',
            'product_name' => $plan->name.' plan',
            'product_category' => 'subscription',
            'shipping_method' => 'NO',
            'product_profile' => 'general',
        ]);

        if (! $response->successful() || empty($response->json('GatewayPageURL'))) {
            $payment->update(['status' => 'failed']);

            throw ValidationException::withMessages([
                'gateway' => ['Could not start SSLCommerz checkout.'],
            ]);
        }

        $payment->update([
            'reference' => $response->json('sessionkey'),
            'metadata' => ['gateway_page_url' => $response->json('GatewayPageURL')],
        ]);

        return [
            'status' => 'redirect',
            'payment' => $payment->fresh(),
            'redirect_url' => $response->json('GatewayPageURL'),
        ];
    }

    protected function recordPayment(
        Store $store,
        SubscriptionPlan $plan,
        string $gateway,
        float $amount,
        string $status
    ): SubscriptionPayment {
        return SubscriptionPayment::query()->create([
            'store_id' => $store->id,
            'subscription_plan_id' => $plan->id,
            'amount' => $amount,
            'currency' => $store->currency ?? 'BDT',
            'gateway' => $gateway,
            'status' => $status,
            'reference' => null,
            'metadata' => null,
        ]);
    }
}
