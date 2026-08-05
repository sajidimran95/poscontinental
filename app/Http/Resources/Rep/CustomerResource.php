<?php

namespace App\Http\Resources\Rep;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'company_name' => $this->company_name,
            'contact' => $this->contact,
            'telephone' => $this->telephone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'is_inactive' => (bool) $this->is_inactive,
            'price_level_id' => $this->price_level_id,
            'price_level' => $this->whenLoaded('priceLevel', fn () => $this->priceLevel ? [
                'id' => $this->priceLevel->id,
                'name' => $this->priceLevel->name ?? null,
            ] : null),
            'balance' => (float) $this->balance,
            'credit_limit' => (float) $this->credit_limit,
            'available_credit' => (float) $this->available_credit,
            'messages_alerts' => $this->messages_alerts,
            'payment_term_id' => $this->payment_term_id,
            'sales_rep_id' => $this->sales_rep_id,
            'last_order_on' => optional($this->last_order_on)?->toDateString(),
            'customer_since' => optional($this->customer_since)?->toDateString(),
        ];
    }
}
