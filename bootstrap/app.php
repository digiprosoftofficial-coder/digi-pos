<?php

use App\Http\Middleware\CheckCapability;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureStoreIsOperable;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolveStore;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
        $middleware->alias([
            'store' => ResolveStore::class,
            'store_operable' => EnsureStoreIsOperable::class,
            'capability' => CheckCapability::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'super_admin' => EnsureSuperAdmin::class,
            'verified' => EnsureEmailVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
