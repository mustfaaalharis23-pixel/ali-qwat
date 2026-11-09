<?php

namespace App\Lenses;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

/**
 * The inactive items that still have a quantity in the stock. An inactive item is out of the system everywhere,
 * so a quantity left on it is money nobody can sell: this is the one place inactive items are reached on purpose.
 * Each row has an action to activate the item; once activated, the item leaves the lens.
 */
class InactiveItemsWithStockLens extends Lens
{
    public static function uriKey(): string
    {
        return 'inactive-items-with-stock';
    }

    public static function label(): string
    {
        return __('filament/lenses.inactive_items_with_stock.label');
    }

    public static function explanation(): string
    {
        return __('filament/lenses.inactive_items_with_stock.explanation');
    }

    public static function guidance(): string
    {
        return __('filament/lenses.inactive_items_with_stock.guidance');
    }

    public function query(): Builder
    {
        return Item::query()
            ->where('is_active', false)
            ->where('available_quantity', '>', 0);
    }

    public function columns(): array
    {
        return [
            LensColumn::id(),
            LensColumn::image('image', __('filament/resources/item.fields.image')),
            LensColumn::text('name', __('filament/resources/item.fields.name'))->searchable(),
            LensColumn::text('available_quantity', __('filament/resources/item.fields.available_quantity'))->numeric()->color('danger')->sortable(),
            LensColumn::text('incoming_quantity', __('filament/resources/item.fields.incoming_quantity'))->numeric()->color('gray')->sortable(),
            LensColumn::text('sale_price', __('filament/resources/item.fields.sale_price'))->numeric()->sortable(),
        ];
    }

    /**
     * Activate the item, so its quantity can be sold again. Only a user who can edit items gets it.
     */
    public function recordActions(): array
    {
        return ['activate'];
    }
}
