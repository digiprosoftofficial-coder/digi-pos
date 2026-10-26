<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%");
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->ok($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
        ]);

        return $this->created(Customer::create($data));
    }

    public function show(Customer $customer): JsonResponse
    {
        return $this->ok($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $customer->update($data);

        return $this->ok($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->ok(null, 'Deleted.');
    }

    /** Customer statement: sales, due payments and loyalty balance. */
    public function ledger(Customer $customer): JsonResponse
    {
        return $this->ok([
            'customer' => $customer,
            'sales' => $customer->sales()->latest('sold_at')->limit(30)->get(['id', 'invoice_no', 'total', 'paid', 'due', 'payment_status', 'sold_at']),
            'payments' => $customer->payments()->latest('paid_at')->limit(30)->get(),
        ]);
    }

    /** Collect money against a customer's outstanding due. */
    public function collectDue(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', 'in:cash,card,mobile,bank'],
            'sale_id' => ['nullable', 'exists:sales,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($customer, $data, $request) {
            $customer->payments()->create([
                'store_id' => $customer->store_id,
                'customer_id' => $customer->id,
                'sale_id' => $data['sale_id'] ?? null,
                'user_id' => $request->user()->id,
                'method' => $data['method'] ?? 'cash',
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'paid_at' => now(),
            ]);

            $customer->decrement('due_balance', $data['amount']);
            if ($customer->fresh()->due_balance < 0) {
                $customer->update(['due_balance' => 0]);
            }

            // Optionally settle a specific sale's due.
            if (! empty($data['sale_id'])) {
                $sale = $customer->sales()->find($data['sale_id']);
                if ($sale) {
                    $newPaid = (float) $sale->paid + (float) $data['amount'];
                    $newDue = max(0, (float) $sale->total - $newPaid);
                    $sale->update([
                        'paid' => $newPaid,
                        'due' => $newDue,
                        'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
                    ]);
                }
            }
        });

        return $this->ok($customer->fresh(), 'Payment recorded.');
    }
}
