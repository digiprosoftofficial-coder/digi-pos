<?php

namespace App\Http\Middleware;

use App\Services\CapabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a store capability (feature flag) that the
 * super admin / subscription plan controls. Usage: ->middleware('capability:reports')
 */
class CheckCapability
{
    public function __construct(protected CapabilityService $capabilities) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();

        // Super admin bypasses capability checks.
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user || ! $this->capabilities->allows($user->store_id, $capability)) {
            return response()->json([
                'success' => false,
                'message' => "Your plan does not include this feature ({$capability}).",
            ], 403);
        }

        return $next($request);
    }
}
