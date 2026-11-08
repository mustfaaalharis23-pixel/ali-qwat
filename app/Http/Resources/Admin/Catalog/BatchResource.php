<?php

namespace App\Http\Resources\Admin\Catalog;

use App\Models\Batch;
use App\Models\BatchColor;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A stock batch, read only: its item, its power, the pieces of each of its colors, its purchase invoice and that invoice's status.
 * The invoice is a link (invoice_path) only for a user who can open the invoice, as on the panel.
 *
 * @mixin Batch
 */
class BatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invoice = $this->invoice;

        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'item' => $this->item === null ? null : ['id' => $this->item->id, 'name' => $this->item->name],
            'lens_power_id' => $this->lens_power_id,
            'lens_power' => $this->lensPower?->value,
            'colors' => $this->colors->map(fn (BatchColor $color): array => ['color' => $color->color?->name, 'pieces' => (float) $color->pieces])->values()->all(),
            'invoice_id' => $this->invoice_id,
            'invoice_path' => $invoice !== null && $this->canOpen($request, $invoice) ? "/purchase-invoices/{$invoice->id}" : null,
            'date' => $invoice?->date?->toDateString(),
            'quantity' => (float) $this->quantity,
            'purchase_price' => (float) $this->purchase_price,
            'status' => $invoice?->status === null ? null : [
                'value' => $invoice->status->value,
                'label' => $invoice->status->getLabel(),
                'color' => $invoice->status->getColor(),
            ],
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Whether the user can open the purchase invoice. The invoice policy's view ability does not depend on the invoice,
     * so it is asked once for the whole list.
     */
    private function canOpen(Request $request, Invoice $invoice): bool
    {
        if (! $request->attributes->has('admin.catalog.can_view_invoices')) {
            $request->attributes->set('admin.catalog.can_view_invoices', Gate::allows('view', $invoice));
        }

        return (bool) $request->attributes->get('admin.catalog.can_view_invoices');
    }
}
