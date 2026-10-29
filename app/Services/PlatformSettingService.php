<?php

namespace App\Services;

use App\Models\Setting;

class PlatformSettingService
{
    public const TRIAL_DAYS_KEY = 'trial_days';

    public const MIN_TRIAL_DAYS = 1;

    public const MAX_TRIAL_DAYS = 365;

    public function trialDays(): int
    {
        $stored = Setting::query()->where('key', self::TRIAL_DAYS_KEY)->value('value');
        $days = $stored !== null
            ? (int) $stored
            : (int) config('billing.trial_days', 7);

        return $this->clampTrialDays($days);
    }

    public function setTrialDays(int $days): int
    {
        $days = $this->clampTrialDays($days);

        Setting::query()->updateOrCreate(
            ['key' => self::TRIAL_DAYS_KEY],
            ['value' => (string) $days],
        );

        return $days;
    }

    public function clampTrialDays(int $days): int
    {
        return max(self::MIN_TRIAL_DAYS, min(self::MAX_TRIAL_DAYS, $days));
    }
}
