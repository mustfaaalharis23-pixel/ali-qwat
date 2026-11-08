<?php

namespace App\Http\Resources\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\LensPower;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LensPower
 */
class LensPowerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'invoice_item_groups_count' => $this->whenCounted('invoiceItemGroups'),
            'is_used' => (bool) ($this->is_used ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => ListsRecords::abilitiesOf($this->resource),
        ];
    }
}
