<?php

namespace App\Http\Resources\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category of the items, with how many items it has. A category used by any item cannot be deleted,
 * so its row offers no delete.
 *
 * @mixin ItemCategory
 */
class ItemCategoryResource extends JsonResource
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
            'items_count' => $this->whenCounted('items'),
            'is_used' => $isUsed,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => [...$abilities, 'delete' => $abilities['delete'] && ! $isUsed, 'force_delete' => $abilities['force_delete'] && ! $isUsed],
        ];
    }
}
