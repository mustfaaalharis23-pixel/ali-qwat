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
     * Its sale price is for a dozen, and its stock is in dozens. It is sold by the whole packet only, of its pieces per packet.
     * The powers are the lens powers it can be sold at, with the dozens left of each; an item without powers has an empty list.
     * The packet types are what the seller chooses from: each power with each mix of colors in its packets, with the packets
     * and the dozens left of it.
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
            'pieces_per_packet' => $this->pieces_per_packet,
            'available_quantity' => round((float) $this->available_quantity, 2),
            'incoming_quantity' => round((float) $this->incoming_quantity, 2),
            'is_out_of_stock' => (float) $this->available_quantity <= 0,
            'powers' => $this->powersLeft(),
            'packet_types' => $this->packetTypesLeft(),
        ];
    }
}
