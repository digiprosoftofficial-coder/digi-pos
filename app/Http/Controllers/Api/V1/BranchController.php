<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        return $this->ok(Branch::orderByDesc('is_main')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $branch = Branch::create($data);
        $this->ensureSingleMain($branch);

        return $this->created($branch->fresh());
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $branch->update($this->validateData($request));
        $this->ensureSingleMain($branch);

        return $this->ok($branch->fresh());
    }

    public function destroy(Branch $branch): JsonResponse
    {
        if ($branch->is_main) {
            return $this->error('Cannot delete the main branch.', 422);
        }
        $branch->delete();

        return $this->ok(null, 'Deleted.');
    }

    protected function ensureSingleMain(Branch $branch): void
    {
        if ($branch->is_main) {
            Branch::where('id', '!=', $branch->id)->update(['is_main' => false]);
        }
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_main' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }
}
