<?php

namespace App\Http\Resources\Api\V1;

use App\Invoices\PurchaseLabelDocument;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One purchase invoice as the application shows it, in the receiving list and after its carton label is scanned:
 * its label number, its name, its supplier, its date, and its stage. Opened on its own, with its lines loaded, it also
 * carries its total cartons, pieces, and CBM, and its lines. It never carries an amount.
 *
 * @mixin Invoice
 */
class PurchaseInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => PurchaseLabelDocument::number($this->resource),
            'name' => $this->name,
            'supplier' => $this->supplier?->name_ar,
            'date' => $this->date->toDateString(),
            'stage' => [
                'value' => $this->stage->value,
                'label' => $this->stage->getLabel(),
            ],
            $this->mergeWhen($this->relationLoaded('items'), fn (): array => [
                'cartons_count' => (float) $this->items->sum('cartons_count'),
                'quantity' => (float) $this->items->sum('quantity'),
                'cbm' => $this->cbm,
                'items' => PurchaseInvoiceItemResource::collection($this->items),
            ]),
        ];
    }
}
