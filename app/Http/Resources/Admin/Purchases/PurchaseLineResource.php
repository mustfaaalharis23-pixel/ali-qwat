<?php

namespace App\Http\Resources\Admin\Purchases;

use App\Models\InvoiceItem;
use App\Models\InvoiceItemColor;
use App\Models\InvoiceItemGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A line of a purchase invoice: what the edit form fills (the item's description, its packing, its unit cost, its groups and colors)
 * and what the invoice's page shows (its pieces, cartons, CBM, and its costs in yuan, dollars, and dinars as saved by the model).
 * A line saved without a unit cost is shown by its price over its pieces, as the panel's form does.
 *
 * @mixin InvoiceItem
 */
class PurchaseLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity = (float) $this->quantity;
        $unitCost = $this->unit_cost ?? ($quantity > 0 ? round((float) $this->price / $quantity, 2) : 0);

        return [
            'id' => $this->id,
            'specific_description_id' => $this->specific_description_id,
            'description' => $this->specificDescription?->name,
            'item' => $this->item === null ? null : [
                'id' => $this->item->id,
                'name' => $this->item->name,
                'image_url' => filled($this->item->image) ? Storage::disk('public')->url($this->item->image) : null,
                'pieces_per_packet' => $this->item->pieces_per_packet,
            ],
            'entry_unit' => 'packets',
            'boxes_count' => $this->boxes_count,
            'pieces_per_packet' => $this->pieces_per_packet,
            'boxes_per_carton' => $this->boxes_per_carton,
            'carton_cbm' => $this->carton_cbm,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'cartons_count' => $this->cartons_count,
            'cbm' => (float) $this->cbm,
            'price' => (float) $this->price,
            'unit_cost_usd' => $this->unit_cost_usd,
            'price_usd' => $this->price_usd,
            'transport_cost_usd' => $this->transport_cost_usd,
            'transport_cost_per_piece_usd' => $this->transport_cost_per_piece_usd,
            'unit_cost_iqd' => $this->unit_cost_iqd,
            'price_iqd' => $this->price_iqd,
            'transport_cost_iqd' => $this->transport_cost_iqd,
            'transport_cost_per_piece_iqd' => $this->transport_cost_per_piece_iqd,
            'final_unit_cost_iqd' => $this->final_unit_cost_iqd,
            'groups' => $this->groups->map(fn (InvoiceItemGroup $group): array => [
                'id' => $group->id,
                'lens_power_id' => $group->lens_power_id,
                'lens_power' => $group->lensPower?->inHundreds(),
                'boxes_count' => $group->boxes_count,
            ])->values()->all(),
            'colors' => $this->colors->map(fn (InvoiceItemColor $color): array => [
                'id' => $color->id,
                'color_id' => $color->color_id,
                'color' => $color->color?->name,
                'pieces' => $color->pieces,
            ])->values()->all(),
            'colors_description' => $this->colorsDescription(),
        ];
    }
}
