<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    use ApiResponses;

    public function __construct(protected ReturnService $returns) {}

    public function index(Request $request): JsonResponse
    {
        $returns = SaleReturn::query()
            ->with(['customer', 'sale:id,invoice_no'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->sale_id, fn ($q) => $q->where('sale_id', $request->sale_id))
            ->latest('returned_at')
            ->paginate($request->integer('per_page', 20));

        return $this->ok($returns);
    }

    public function show(SaleReturn $saleReturn): JsonResponse
    {
        return $this->ok($saleReturn->load(['items', 'customer', 'sale:id,invoice_no']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'refund_method' => ['nullable', 'in:cash,due_adjust,store_credit'],
            'restock' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $return = $this->returns->create($data, $request->user()->id);

        return $this->created($return, 'Return processed.');
    }
}
