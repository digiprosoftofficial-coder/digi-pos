<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    /**
     * @param  list<string>  $except
     */
    public function handle(Request $request, Closure $next, string ...$except): Response
    {
        if (! config('app.require_email_verification')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->hasVerifiedEmail()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), $except, true)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please verify your email address to continue.',
            'code' => 'email_unverified',
        ], 403);
    }
}
