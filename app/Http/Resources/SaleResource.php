<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),
            'user_id' => $this->user_id,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'paid' => (float) $this->paid,
            'change' => (float) $this->change,
            'due' => (float) $this->due,
            'returned_total' => (float) $this->returned_total,
            'points_earned' => (int) $this->points_earned,
            'points_redeemed' => (int) $this->points_redeemed,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'note' => $this->note,
            'sold_at' => $this->sold_at,
            'items' => $this->whenLoaded('items'),
            'payments' => $this->whenLoaded('payments'),
        ];
    }
}
