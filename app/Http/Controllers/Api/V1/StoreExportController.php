<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Support\StoreContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $storeId = StoreContext::id();
        $filename = 'digipro-store-'.$storeId.'-'.now()->format('Y-m-d').'.json';

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'store_id' => $storeId,
            'products' => Product::with('variants')->get(),
            'categories' => Category::all(['id', 'name', 'parent_id', 'is_active']),
            'customers' => Customer::all(['id', 'name', 'phone', 'email', 'due_balance', 'loyalty_points']),
        ];

        $callback = static function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
