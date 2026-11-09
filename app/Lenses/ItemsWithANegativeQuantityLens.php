<?php

namespace App\Lenses;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

/**
 * The active items whose available quantity is under zero: more was sold of them than ever came in on the purchase
 * invoices both sides confirmed. The number is never entered by hand, so it means the purchases of the item are
 * missing an invoice, or a confirmed invoice was deleted after the selling.
 * Each row has an action to work the quantity out again from the purchases and the orders.
 */
class ItemsWithANegativeQuantityLens extends Lens
{
    public static function uriKey(): string
    {
        return 'items-with-a-negative-quantity';
    }

    public static function label(): string
    {
        return __('filament/lenses.items_with_a_negative_quantity.label');
    }

    public static function explanation(): string
    {
        return __('filament/lenses.items_with_a_negative_quantity.explanation');
    }

    public static function guidance(): string
    {
        return __('filament/lenses.items_with_a_negative_quantity.guidance');
    }

    /**
     * The sold quantity is read with the items, in the same query, instead of once per row.
     */
    public function query(): Builder
    {
        return Item::withSoldQuantity(Item::query())
            ->active()
            ->where('available_quantity', '<', 0);
    }

    public function columns(): array
    {
        return [
            LensColumn::id(),
            LensColumn::image('image', __('filament/resources/item.fields.image')),
            LensColumn::text('name', __('filament/resources/item.fields.name'))->searchable(),
            LensColumn::text('available_quantity', __('filament/resources/item.fields.available_quantity'))->numeric()->color('danger')->sortable(),
            LensColumn::text('incoming_quantity', __('filament/resources/item.fields.incoming_quantity'))->numeric()->color('gray')->sortable(),
            LensColumn::text('sold_quantity', __('filament/resources/item.fields.sold_quantity'))
                ->state(fn (Item $record): float => $record->soldQuantity())
                ->numeric(),
        ];
    }

    /**
     * Works the item's quantities and cost out again, from the purchases and the orders themselves.
     * A number left over from an older write is fixed there; a quantity that is really negative stays,
     * and its purchase invoice is what needs the entry. Only a user who can edit items gets it.
     */
    public function recordActions(): array
    {
        return ['recalculateStock'];
    }
}
