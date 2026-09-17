<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * One order as the app shows it in the list: its customer, what it came to, and how many lines are on it.
     * What was paid on it and what is still owed are never sent to the application; they are seen in the panel only.
     * The payment type comes with its translated label, so the app shows it in the language of the request.
     * The sold lines are there only when they are loaded, so the list stays light and one order carries its details.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->customer?->name,
            ],
            'payment_type' => [
                'value' => $this->payment_type->value,
                'label' => $this->payment_type->getLabel(),
            ],
            'amount' => $this->amount,
            'total_amount' => $this->total_amount,
            'items_count' => $this->items_count,
            'notes' => $this->notes,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
