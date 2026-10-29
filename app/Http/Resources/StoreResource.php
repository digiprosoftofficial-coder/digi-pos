<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo' => $this->logo,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'default_tax_rate' => $this->default_tax_rate,
            'locale' => $this->locale,
            'receipt_header' => $this->receipt_header,
            'receipt_footer' => $this->receipt_footer,
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at,
            'loyalty' => $this->settings['loyalty'] ?? [
                'enabled' => false,
                'earn_per_unit' => 0,
                'redeem_value' => 0,
            ],
        ];
    }
}
