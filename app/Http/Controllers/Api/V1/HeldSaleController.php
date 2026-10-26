<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\HeldSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HeldSaleController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        return $this->ok(HeldSale::latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'cart' => ['required', 'array'],
        ]);

        $held = HeldSale::create([
            'user_id' => $request->user()->id,
            'customer_id' => $data['customer_id'] ?? null,
            'reference' => $data['reference'] ?? 'HOLD-'.Str::upper(Str::random(5)),
            'cart' => $data['cart'],
        ]);

        return $this->created($held);
    }

    public function destroy(HeldSale $heldSale): JsonResponse
    {
        $heldSale->delete();

        return $this->ok(null, 'Removed.');
    }
}
