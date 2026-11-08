<?php

namespace App\Http\Resources\Admin\System;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\SpecificDescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A specific description. An item's description follows its item, so it is never edited or deleted from the screens.
 *
 * @mixin SpecificDescription
 */
class SpecificDescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'general_description_id' => $this->general_description_id,
            'general_description' => $this->generalDescription === null ? null : [
                'id' => $this->generalDescription->id,
                'name' => $this->generalDescription->name,
                'type' => EnumValue::of($this->generalDescription->type),
                'is_purchases_description' => (bool) $this->generalDescription->is_purchases_description,
            ],
            'item' => $this->item === null ? null : ['id' => $this->item->id, 'name' => $this->item->name],
            'is_item_description' => $this->item_id !== null,
            'transfer_type' => EnumValue::of($this->transfer_type),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => self::abilities($this->resource),
        ];
    }

    /**
     * @return array{view: bool, update: bool, delete: bool, restore: bool, force_delete: bool}
     */
    public static function abilities(SpecificDescription $description): array
    {
        $can = ListsRecords::abilitiesOf($description);

        if ($description->item_id !== null) {
            $can['update'] = false;
            $can['delete'] = false;
            $can['force_delete'] = false;
        }

        return $can;
    }
}
