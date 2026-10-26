<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $logs = Activity::query()
            ->with('causer:id,name,email')
            ->when($request->log_name, fn ($q) => $q->where('log_name', $request->log_name))
            ->latest()
            ->paginate($request->integer('per_page', 30));

        return $this->ok($logs);
    }
}
