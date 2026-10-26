<?php

use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\StoreController as AdminStoreController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BarcodeController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HeldSaleController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductLookupController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\StoreExportController;
use App\Http\Controllers\Api\V1\StoreSettingsController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth endpoints (rate limited)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('auth/email/verify', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('api.verification.verify');

    // Authenticated + tenant-scoped endpoints
    Route::middleware(['auth:sanctum', 'store'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:auth');

        Route::middleware('verified')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Catalog (gated behind the "products" capability)
        Route::middleware('capability:products')->group(function () {
            Route::apiResource('categories', CategoryController::class)->except('show');
            Route::apiResource('brands', BrandController::class)->except('show');
            Route::apiResource('units', UnitController::class)->except('show');
            Route::apiResource('products', ProductController::class);
            Route::post('products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);
            Route::get('products/{product}/stock-movements', [ProductController::class, 'stockMovements']);
            Route::post('products/{product}/image', [ProductController::class, 'uploadImage']);
            Route::delete('products/{product}/image', [ProductController::class, 'deleteImage']);
            Route::post('products/{product}/variants/{variant}/image', [ProductController::class, 'uploadVariantImage']);
            Route::delete('products/{product}/variants/{variant}/image', [ProductController::class, 'deleteVariantImage']);
        });

        // POS product lookup
        Route::get('lookup/scan', [ProductLookupController::class, 'scan']);
        Route::get('lookup/search', [ProductLookupController::class, 'search']);
        Route::get('lookup/catalog', [ProductLookupController::class, 'catalog']);

        // Customers (+ due collection / loyalty ledger)
        Route::middleware('capability:customers')->group(function () {
            Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger']);
            Route::post('customers/{customer}/collect-due', [CustomerController::class, 'collectDue']);
            Route::apiResource('customers', CustomerController::class);
        });

        // Sales / POS
        Route::apiResource('sales', SaleController::class)->only(['index', 'show', 'store']);
        Route::get('sales/{sale}/receipt', [ReceiptController::class, 'pdf']);
        Route::apiResource('held-sales', HeldSaleController::class)->only(['index', 'store', 'destroy']);

        // Suppliers & Purchases
        Route::middleware('capability:purchases')->group(function () {
            Route::post('suppliers/{supplier}/pay', [SupplierController::class, 'pay']);
            Route::apiResource('suppliers', SupplierController::class);
            Route::apiResource('purchases', PurchaseController::class)->only(['index', 'show', 'store']);
        });

        // Sales Returns
        Route::apiResource('sale-returns', SaleReturnController::class)
            ->only(['index', 'show', 'store'])
            ->middleware('capability:returns');

        // Multi-branch / Outlets
        Route::apiResource('branches', BranchController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('capability:multi_branch');

        // Reports (basic KPIs for every store; CSV export is the advanced add-on)
        Route::middleware('capability:reports.basic')->prefix('reports')->group(function () {
            Route::get('summary', [ReportController::class, 'summary']);
            Route::get('sales', [ReportController::class, 'sales']);
            Route::get('products', [ReportController::class, 'products']);
            Route::get('inventory', [ReportController::class, 'inventory']);
            Route::get('dues', [ReportController::class, 'dues']);
            Route::get('export', [ReportController::class, 'export'])->middleware('capability:reports.advanced');
        });

        // Barcode labels
        Route::get('barcode/svg', [BarcodeController::class, 'svg'])->middleware('capability:barcode_print');

        // Store users & settings (owner / manager)
        Route::get('roles', [UserController::class, 'roles']);
        Route::apiResource('users', UserController::class)->except('show')->middleware('capability:staff');
        Route::get('settings/store', [StoreSettingsController::class, 'show']);
        Route::put('settings/store', [StoreSettingsController::class, 'update']);
        Route::get('settings/export', StoreExportController::class)->middleware('permission:settings.manage');

        Route::get('billing/plans', [BillingController::class, 'plans'])->middleware('permission:settings.manage');
        Route::post('billing/checkout', [BillingController::class, 'checkout'])->middleware('permission:settings.manage');
        });
    });

    // Super admin platform endpoints
    Route::middleware(['auth:sanctum', 'super_admin'])->prefix('admin')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        Route::get('stores', [AdminStoreController::class, 'index']);
        Route::get('stores/{store}', [AdminStoreController::class, 'show']);
        Route::patch('stores/{store}/status', [AdminStoreController::class, 'updateStatus']);
        Route::delete('stores/{store}', [AdminStoreController::class, 'destroy']);
        Route::get('stores/{store}/capabilities', [AdminStoreController::class, 'capabilities']);
        Route::put('stores/{store}/capabilities', [AdminStoreController::class, 'setCapabilities']);
        Route::post('stores/{store}/plan', [AdminStoreController::class, 'assignPlan']);
        Route::post('stores/{store}/impersonate', [AdminStoreController::class, 'impersonate']);

        Route::get('plans/capabilities', [SubscriptionPlanController::class, 'capabilities']);
        Route::apiResource('plans', SubscriptionPlanController::class)->except('show');

        Route::get('activity', [ActivityLogController::class, 'index']);
    });
});
