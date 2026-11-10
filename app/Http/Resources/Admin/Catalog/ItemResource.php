<?php

namespace App\Http\Resources\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * An item as the admin application shows it. The cost price and the profit margin are only in the answer for a user
 * who may see what the items cost us (item.view_cost_price), exactly as the panel showed them.
 * The quantities by power and by color are added on the item's own page only (withQuantities()).
 *
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    private bool $withQuantities = false;

    /**
     * Adds what came in of each power and of each color, for the item's page.
     */
    public function withQuantities(): static
    {
        $this->withQuantities = true;

        return $this;
    }

    /**
     * Whether the signed-in user may see what the items cost us, asked once for the whole answer.
     */
    public static function canSeeTheCost(Request $request): bool
    {
        if (! $request->attributes->has('admin.catalog.can_view_cost_price')) {
            $request->attributes->set('admin.catalog.can_view_cost_price', Gate::allows('viewCostPrice', Item::class));
        }

        return (bool) $request->attributes->get('admin.catalog.can_view_cost_price');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeTheCost = self::canSeeTheCost($request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'image_url' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'description' => $this->description,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn (): ?array => $this->supplier === null ? null : ['id' => $this->supplier->id, 'name' => $this->supplier->name_ar]),
            'pieces_per_packet' => $this->pieces_per_packet,
            'sale_price' => (float) $this->sale_price,
            'piece_sale_price' => Item::piecePrice((float) $this->sale_price),
            'cost_price' => $this->when($canSeeTheCost, fn (): float => (float) $this->cost_price),
            'piece_cost_price' => $this->when($canSeeTheCost, fn (): float => Item::piecePrice((float) $this->cost_price)),
            'profit_margin' => $this->when($canSeeTheCost, fn (): float => $this->profitMargin()),
            'available_quantity' => (float) $this->available_quantity,
            'incoming_quantity' => (float) $this->incoming_quantity,
            'is_active' => (bool) $this->is_active,
            'has_powers' => $this->when($this->withQuantities, fn (): bool => $this->quantitiesByPower()->contains(fn (array $row): bool => $row['power'] !== null)),
            'quantities_by_power' => $this->when($this->withQuantities, fn (): array => $this->quantitiesByPower()->all()),
            'quantities_by_color' => $this->when($this->withQuantities, fn (): array => $this->quantitiesByColor()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'can' => ListsRecords::abilitiesOf($this->resource, editableWhenDeleted: true),
        ];
    }
}
