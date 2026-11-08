<?php

namespace App\Http\Resources\Admin\System;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\GeneralDescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A general description. The purchases description is never deleted from the screens, because every item's description is under it,
 * and its specific descriptions are read only there, because they are created with the items.
 *
 * @mixin GeneralDescription
 */
class GeneralDescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $can = ListsRecords::abilitiesOf($this->resource);

        if ($this->is_purchases_description) {
            $can['delete'] = false;
            $can['force_delete'] = false;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => EnumValue::of($this->type),
            'is_purchases_description' => (bool) $this->is_purchases_description,
            'is_capital_movement' => (bool) $this->is_capital_movement,
            'specific_descriptions_count' => $this->whenCounted('specificDescriptions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => $can,
        ];
    }
}
