<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    use ApiResponses;

    public function __construct(protected PurchaseService $purchases) {}

    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::query()
            ->with('supplier')
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%")
                ->orWhere('supplier_invoice', 'like', "%{$request->search}%"))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn ($q) => $q->whereDate('purchased_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('purchased_at', '<=', $request->date_to))
            ->latest('purchased_at')
            ->paginate($request->integer('per_page', 20));

        return $this->ok($purchases);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return $this->ok($purchase->load(['items', 'supplier', 'payments']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'supplier_invoice' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,card,mobile,bank,bkash'],
            'status' => ['nullable', 'in:pending,received'],
            'update_cost' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchase = $this->purchases->create($data, $request->user()->id);

        return $this->created($purchase, 'Purchase recorded.');
    }
}
