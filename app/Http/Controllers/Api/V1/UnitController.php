<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        return $this->ok(Unit::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:16'],
        ]);

        return $this->created(Unit::create($data));
    }

    public function update(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['nullable', 'string', 'max:16'],
        ]);

        $unit->update($data);

        return $this->ok($unit);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $unit->delete();

        return $this->ok(null, 'Deleted.');
    }
}
