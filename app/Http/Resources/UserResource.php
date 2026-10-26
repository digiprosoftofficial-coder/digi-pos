<?php

namespace App\Http\Resources;

use App\Services\CapabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'email_verified' => $this->hasVerifiedEmail(),
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'is_super_admin' => (bool) $this->is_super_admin,
            'is_active' => (bool) $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'capabilities' => $this->when(
                ! $this->is_super_admin && $this->store_id,
                fn () => app(CapabilityService::class)->effectiveFor((int) $this->store_id)
            ),
            'store' => new StoreResource($this->whenLoaded('store')),
        ];
    }
}
