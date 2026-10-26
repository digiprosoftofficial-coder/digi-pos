<?php

namespace App\Http\Middleware;

use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant (store) from the authenticated user
 * and applies it to the StoreContext so global scopes take effect.
 */
class ResolveStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()) {
            if (! $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No store is associated with this account.',
                ], 403);
            }

            if ($user->store && ! $user->store->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This store is currently suspended. Please contact support.',
                ], 403);
            }

            StoreContext::set((int) $user->store_id);
        }

        return $next($request);
    }
}
