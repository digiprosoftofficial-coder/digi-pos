<?php

namespace App\Http\Requests\Admin;

use App\Services\PlatformSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'start_mode' => ['required', 'in:trial,active'],
            'trial_days' => [
                'nullable',
                'integer',
                'min:'.PlatformSettingService::MIN_TRIAL_DAYS,
                'max:'.PlatformSettingService::MAX_TRIAL_DAYS,
            ],
        ];
    }
}
