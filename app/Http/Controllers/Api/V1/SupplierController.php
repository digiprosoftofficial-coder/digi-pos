<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('company', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%"));
            })
            ->when($request->boolean('with_due'), fn ($q) => $q->where('due_balance', '>', 0))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->ok($suppliers);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->ok($supplier->load(['purchases' => fn ($q) => $q->latest()->limit(20), 'payments' => fn ($q) => $q->latest()->limit(20)]));
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = Supplier::create($this->validateData($request));

        return $this->created($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->validateData($request));

        return $this->ok($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return $this->ok(null, 'Deleted.');
    }

    /** Pay down a supplier's outstanding balance. */
    public function pay(Request $request, Supplier $supplier, PurchaseService $service): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', 'in:cash,card,mobile,bank'],
            'purchase_id' => ['nullable', 'exists:purchases,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $service->paySupplier($supplier, $data, $request->user()->id);

        return $this->ok($supplier->fresh(), 'Payment recorded.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);
    }
}
