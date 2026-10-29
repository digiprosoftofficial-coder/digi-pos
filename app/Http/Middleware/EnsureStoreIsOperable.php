<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreIsOperable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $store = $user->store;

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'No store is associated with this account.',
                'code' => 'store_missing',
            ], 403);
        }

        $store->expireTrialIfDue();
        $store->refresh();

        if ($store->isActive()) {
            return $next($request);
        }

        $expired = $store->status === 'expired';

        return response()->json([
            'success' => false,
            'message' => $expired
                ? 'Your trial has ended. Please choose a plan to continue.'
                : 'This store is currently suspended. Please contact support.',
            'code' => $expired ? 'trial_expired' : 'store_inactive',
        ], 403);
    }
}
