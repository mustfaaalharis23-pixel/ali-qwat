<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    /**
     * One item as the app shows it for selling: its price, what is in stock now, and what is on its way.
     * An item with nothing in stock is still shown, with is_out_of_stock so the app marks it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'sale_price' => $this->sale_price,
            'available_quantity' => $this->available_quantity,
            'incoming_quantity' => $this->incoming_quantity,
            'is_out_of_stock' => (float) $this->available_quantity <= 0,
        ];
    }
}
