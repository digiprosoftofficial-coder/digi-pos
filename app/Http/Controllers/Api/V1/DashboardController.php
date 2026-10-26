<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\StoreContext;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        $storeId = StoreContext::id();
        $today = today();

        $todaySales = Sale::where('status', 'completed')->whereDate('sold_at', $today);

        $topProducts = SaleItem::query()
            ->where('sale_items.store_id', $storeId)
            ->selectRaw('name, SUM(quantity) as qty, SUM(subtotal) as revenue')
            ->groupBy('name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        $lowStock = Product::where('track_stock', true)
            ->whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get(['id', 'name', 'stock_quantity', 'alert_quantity']);

        $recentSales = Sale::with('customer')
            ->latest('sold_at')
            ->limit(10)
            ->get(['id', 'invoice_no', 'total', 'payment_status', 'customer_id', 'sold_at']);

        return $this->ok([
            'today' => [
                'sales_total' => (float) (clone $todaySales)->sum('total'),
                'sales_count' => (clone $todaySales)->count(),
                'due_total' => (float) (clone $todaySales)->sum('due'),
            ],
            'totals' => [
                'products' => Product::count(),
                'customers' => Customer::count(),
                'sales' => Sale::where('status', 'completed')->count(),
                'revenue' => (float) Sale::where('status', 'completed')->sum('total'),
            ],
            'top_products' => $topProducts,
            'low_stock' => $lowStock,
            'recent_sales' => $recentSales,
        ]);
    }
}
