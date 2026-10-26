<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->get();

        return $this->ok($brands);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]);

        return $this->created(Brand::create($data));
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]);

        $brand->update($data);

        return $this->ok($brand);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return $this->ok(null, 'Deleted.');
    }
}
