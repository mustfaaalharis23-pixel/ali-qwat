<?php

namespace App\Http\Resources\Admin\Purchases;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A container: its numbers, its own price of one CBM (a note only), its dates and notes, and, in a list, the CBM and the
 * transport cost of its invoices. A container that has invoices cannot be deleted, so it says whether it has any.
 *
 * @mixin Container
 */
class ContainerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $abilities = ListsRecords::abilitiesOf($this->resource);
        $hasInvoices = (bool) ($this->has_invoices ?? false);
        $hasAnyInvoices = (bool) ($this->has_any_invoices ?? false);

        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'international_number' => $this->international_number,
            'cbm_price' => $this->cbm_price,
            'loading_date' => $this->loading_date?->toDateString(),
            'expected_arrival_date' => $this->expected_arrival_date?->toDateString(),
            'notes' => $this->notes,
            'total_cbm' => (float) ($this->invoices_sum_cbm ?? 0),
            'transport_cost' => (float) ($this->invoices_sum_transport_cost_usd ?? 0),
            'has_invoices' => $hasInvoices,
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => [
                ...$abilities,
                'delete' => $abilities['delete'] && ! $hasInvoices,
                'force_delete' => $abilities['force_delete'] && ! $hasAnyInvoices,
                'change_invoices' => ! $this->trashed() && $abilities['update'],
            ],
        ];
    }
}
