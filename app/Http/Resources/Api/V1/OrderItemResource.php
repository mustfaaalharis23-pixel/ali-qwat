<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One sold line of an order: the item, the lens power it was sold at (null without one) and the colors of one of its packets,
 * its packets, its pieces, its dozens (the quantity the price is for), its package (with the pieces its packet held when sold),
 * its price of a dozen, and the total of the line. The cost and the profit of the line stay in the panel and are never sent to the application.
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
            'lens_power' => $this->lensPower === null ? null : [
                'id' => $this->lensPower->id,
                'value' => $this->lensPower->value,
            ],
            'colors' => $this->colorsWithNames(),
            'packets' => $this->packets(),
            'pieces' => $this->pieces,
            'quantity' => round((float) $this->quantity, 2),
            'package' => [
                'value' => $this->package->value,
                'label' => $this->package->getLabel(),
                'size' => $this->package_size,
            ],
            'price' => $this->price,
            'total_price' => $this->total_price,
        ];
    }
}
