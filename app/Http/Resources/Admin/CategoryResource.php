<?php

namespace App\Http\Resources\Admin;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $abilities = ListsRecords::abilitiesOf($this->resource);
        $isUsed = ($this->has_linked_items ?? false) || ($this->has_linked_invoices ?? false);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'items_count' => $this->whenCounted('items'),
            'invoices_count' => $this->whenCounted('invoices'),
            'is_used' => $isUsed,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => [...$abilities, 'delete' => $abilities['delete'] && ! $isUsed, 'force_delete' => $abilities['force_delete'] && ! $isUsed],
        ];
    }
}
