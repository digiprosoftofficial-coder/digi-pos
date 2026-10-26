<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\EnsuresStoreOwnership;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    use ApiResponses, EnsuresStoreOwnership;

    public function __construct(protected SaleService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $sales = Sale::query()
            ->with('customer')
            ->when($request->search, fn ($q) => $q->where('invoice_no', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn ($q) => $q->whereDate('sold_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('sold_at', '<=', $request->date_to))
            ->latest('sold_at')
            ->paginate($request->integer('per_page', 20));

        return $this->ok($sales);
    }

    public function show(Sale $sale): JsonResponse
    {
        $this->ensureSameStore($sale);

        return $this->ok(new SaleResource($sale->load(['items', 'payments', 'customer'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,card,mobile,bank,bkash'],
            'note' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:completed,held'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
            'points_redeem' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = $this->sales->checkout($data, $request->user()->id);

        return $this->created(new SaleResource($sale), 'Sale completed.');
    }
}
