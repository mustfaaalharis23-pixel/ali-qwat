<?php

namespace App\Http\Resources\Admin\Purchases;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\InvoiceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category of the purchase invoices, with how many invoices it has. A category used by any invoice cannot be deleted,
 * so its row offers no delete.
 *
 * @mixin InvoiceCategory
 */
class InvoiceCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $abilities = ListsRecords::abilitiesOf($this->resource);
        $isUsed = (bool) ($this->is_used ?? false);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'invoices_count' => $this->whenCounted('invoices'),
            'is_used' => $isUsed,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => [...$abilities, 'delete' => $abilities['delete'] && ! $isUsed, 'force_delete' => $abilities['force_delete'] && ! $isUsed],
        ];
    }
}
