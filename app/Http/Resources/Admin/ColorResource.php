<?php

namespace App\Http\Resources\Admin;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Color
 */
class ColorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'swatch' => Color::swatch($this->name),
            'invoice_item_colors_count' => $this->whenCounted('invoiceItemColors'),
            'is_used' => (bool) ($this->is_used ?? false),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => ListsRecords::abilitiesOf($this->resource),
        ];
    }
}
