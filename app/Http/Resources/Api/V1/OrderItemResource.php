<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One sold line of an order: the item, how many were sold, at what price, and the total of the line.
 * The cost and the profit of the line stay in the panel and are never sent to the application.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'name' => $this->item?->name,
            'image' => $this->item?->image ? Storage::disk('public')->url($this->item->image) : null,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'total_price' => $this->total_price,
        ];
    }
}
