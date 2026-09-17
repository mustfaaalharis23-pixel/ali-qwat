<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * One customer as any screen needs them: how to reach them and how they pay.
     * The debt of a customer is never sent to the application; it is seen in the panel only.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'payment_type' => [
                'value' => $this->payment_type->value,
                'label' => $this->payment_type->getLabel(),
            ],
        ];
    }
}
