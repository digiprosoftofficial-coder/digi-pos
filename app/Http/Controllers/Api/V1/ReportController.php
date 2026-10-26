<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Services\InventoryValuationService;
use App\Support\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ApiResponses;

    public function __construct(protected InventoryValuationService $inventoryValuation) {}

    protected function range(Request $request): array
    {
        return [
            $request->date('date_from')?->startOfDay() ?? now()->startOfMonth(),
            $request->date('date_to')?->endOfDay() ?? now()->endOfDay(),
        ];
    }

    /** Headline KPIs + profit for a date range. */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $branchId = $request->integer('branch_id') ?: null;

        $sales = Sale::query()
            ->where('status', '!=', 'held')
            ->whereBetween('sold_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $grossTotal = (float) (clone $sales)->sum('total');
        $salesCount = (clone $sales)->count();
        $discount = (float) (clone $sales)->sum('discount');
        $tax = (float) (clone $sales)->sum('tax');
        $dueOutstanding = (float) (clone $sales)->sum('due');

        $saleIds = (clone $sales)->pluck('id');
        $cogs = (float) SaleItem::whereIn('sale_id', $saleIds)->sum(DB::raw('unit_cost * quantity'));

        $returnsTotal = (float) SaleReturn::whereBetween('returned_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total');

        $purchasesTotal = (float) Purchase::whereBetween('purchased_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total');

        $netRevenue = round($grossTotal - $returnsTotal, 2);
        $profit = round($netRevenue - $cogs, 2);

        return $this->ok([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'sales_count' => $salesCount,
            'gross_sales' => round($grossTotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'returns' => round($returnsTotal, 2),
            'net_revenue' => $netRevenue,
            'cogs' => round($cogs, 2),
            'gross_profit' => $profit,
            'purchases' => round($purchasesTotal, 2),
            'due_outstanding' => round($dueOutstanding, 2),
        ]);
    }

    /** Daily sales trend. */
    public function sales(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $branchId = $request->integer('branch_id') ?: null;

        $rows = Sale::query()
            ->where('status', '!=', 'held')
            ->whereBetween('sold_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(sold_at) as day, COUNT(*) as orders, SUM(total) as total, SUM(due) as due')
            ->groupByRaw('DATE(sold_at)')
            ->orderBy('day')
            ->get();

        return $this->ok($rows);
    }

    /** Best selling products in range. */
    public function products(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $rows = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('status', '!=', 'held')->whereBetween('sold_at', [$from, $to]))
            ->selectRaw('name, SUM(quantity) as qty, SUM(subtotal) as revenue, SUM((unit_price - unit_cost) * quantity) as profit')
            ->groupBy('name')
            ->orderByDesc('qty')
            ->limit(50)
            ->get();

        return $this->ok($rows);
    }

    /** Current stock valuation and low-stock list. */
    public function inventory(): JsonResponse
    {
        $summary = $this->inventoryValuation->summarize();

        unset($summary['lines']);

        return $this->ok($summary);
    }

    /** Outstanding receivables (customers) and payables (suppliers). */
    public function dues(): JsonResponse
    {
        return $this->ok([
            'receivable' => (float) Customer::where('due_balance', '>', 0)->sum('due_balance'),
            'payable' => (float) Supplier::where('due_balance', '>', 0)->sum('due_balance'),
            'customers' => Customer::where('due_balance', '>', 0)->orderByDesc('due_balance')->limit(100)->get(['id', 'name', 'phone', 'due_balance']),
            'suppliers' => Supplier::where('due_balance', '>', 0)->orderByDesc('due_balance')->limit(100)->get(['id', 'name', 'company', 'phone', 'due_balance']),
        ]);
    }

    /** Stream a CSV export for the given report type. */
    public function export(Request $request): StreamedResponse
    {
        $type = $request->query('type', 'sales');
        [$from, $to] = $this->range($request);
        $branchId = $request->integer('branch_id') ?: null;
        StoreContext::id(); // ensure scope resolved

        $filename = "report-{$type}-{$from->toDateString()}-to-{$to->toDateString()}.csv";
        $inventoryLines = $type === 'inventory'
            ? $this->inventoryValuation->summarize()['lines']
            : [];

        $callback = function () use ($type, $from, $to, $branchId, $inventoryLines) {
            $out = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($out, ['Invoice', 'Date', 'Customer', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Due', 'Status']);
                Sale::with('customer')
                    ->where('status', '!=', 'held')
                    ->whereBetween('sold_at', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->orderBy('sold_at')
                    ->chunk(300, function ($chunk) use ($out) {
                        foreach ($chunk as $s) {
                            fputcsv($out, [
                                $s->invoice_no,
                                optional($s->sold_at)->toDateTimeString(),
                                $s->customer?->name ?? 'Walk-in',
                                $s->subtotal, $s->discount, $s->tax, $s->total, $s->paid, $s->due, $s->payment_status,
                            ]);
                        }
                    });
            } elseif ($type === 'products') {
                fputcsv($out, ['Product', 'Qty Sold', 'Revenue', 'Profit']);
                SaleItem::whereHas('sale', fn ($q) => $q->where('status', '!=', 'held')->whereBetween('sold_at', [$from, $to]))
                    ->selectRaw('name, SUM(quantity) as qty, SUM(subtotal) as revenue, SUM((unit_price - unit_cost) * quantity) as profit')
                    ->groupBy('name')->orderByDesc('qty')
                    ->get()
                    ->each(fn ($r) => fputcsv($out, [$r->name, $r->qty, $r->revenue, $r->profit]));
            } elseif ($type === 'inventory') {
                fputcsv($out, ['Product', 'SKU', 'Stock', 'Cost', 'Retail', 'Stock Value (cost)']);
                foreach ($inventoryLines as $line) {
                    fputcsv($out, [
                        $line['name'],
                        $line['sku'],
                        $line['stock_quantity'],
                        $line['cost_price'],
                        $line['selling_price'],
                        $line['stock_value_cost'],
                    ]);
                }
            } elseif ($type === 'purchases') {
                fputcsv($out, ['Reference', 'Date', 'Supplier', 'Total', 'Paid', 'Due', 'Status']);
                Purchase::with('supplier')
                    ->whereBetween('purchased_at', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->orderBy('purchased_at')
                    ->chunk(300, function ($chunk) use ($out) {
                        foreach ($chunk as $p) {
                            fputcsv($out, [$p->reference, optional($p->purchased_at)->toDateTimeString(), $p->supplier?->name, $p->total, $p->paid, $p->due, $p->payment_status]);
                        }
                    });
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
