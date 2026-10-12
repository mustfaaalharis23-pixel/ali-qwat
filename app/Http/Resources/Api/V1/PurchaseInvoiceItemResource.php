<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One line of a purchase invoice as the receiving page counts it: the item, its packets and the pieces in one packet,
 * its pieces, the packets one carton holds, its cartons, and the space it takes. Its prices and costs stay in the panel.
 *
 * @mixin InvoiceItem
 */
class PurchaseInvoiceItemResource extends JsonResource
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
            'boxes_count' => $this->boxes_count,
            'box_quantity' => $this->box_quantity,
            'quantity' => $this->quantity,
            'boxes_per_carton' => $this->boxes_per_carton,
            'cartons_count' => $this->cartons_count,
            'cbm' => $this->cbm,
        ];
    }
}
