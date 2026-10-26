<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        return $this->ok([
            'stores' => [
                'total' => Store::count(),
                'active' => Store::where('status', 'active')->count(),
                'trial' => Store::where('status', 'trial')->count(),
                'suspended' => Store::where('status', 'suspended')->count(),
            ],
            'users' => User::where('is_super_admin', false)->count(),
            'sales' => [
                'count' => Sale::where('status', 'completed')->count(),
                'revenue' => (float) Sale::where('status', 'completed')->sum('total'),
            ],
            'recent_stores' => Store::latest()->limit(8)->get(['id', 'name', 'status', 'created_at']),
        ]);
    }
}
