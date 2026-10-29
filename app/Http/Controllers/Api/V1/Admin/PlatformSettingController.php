<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Services\PlatformSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    use ApiResponses;

    public function __construct(protected PlatformSettingService $settings) {}

    public function show(): JsonResponse
    {
        return $this->ok([
            'trial_days' => $this->settings->trialDays(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trial_days' => [
                'required',
                'integer',
                'min:'.PlatformSettingService::MIN_TRIAL_DAYS,
                'max:'.PlatformSettingService::MAX_TRIAL_DAYS,
            ],
        ]);

        return $this->ok([
            'trial_days' => $this->settings->setTrialDays((int) $data['trial_days']),
        ], 'Platform settings saved.');
    }
}
