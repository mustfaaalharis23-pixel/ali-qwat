<?php

namespace App\Lenses;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

/**
 * The active items that were never sold: not one unit of them is on any order that is not deleted.
 * An item that was never bought either is here too, because nothing was sold of it.
 * Deleted items are not shown. The item leaves the lens on its first sale.
 */
class ItemsWithoutSalesLens extends Lens
{
    public static function uriKey(): string
    {
        return 'items-without-sales';
    }

    public static function label(): string
    {
        return __('filament/lenses.items_without_sales.label');
    }

    public static function explanation(): string
    {
        return __('filament/lenses.items_without_sales.explanation');
    }

    public static function guidance(): string
    {
        return __('filament/lenses.items_without_sales.guidance');
    }

    /**
     * A sale line on a deleted order does not count, exactly like everywhere else the sold quantity is read.
     */
    public function query(): Builder
    {
        return Item::query()
            ->active()
            ->whereDoesntHave('orderItems', fn (Builder $query): Builder => $query->whereHas('order'));
    }

    public function columns(): array
    {
        return [
            LensColumn::id(),
            LensColumn::image('image', __('filament/resources/item.fields.image')),
            LensColumn::text('name', __('filament/resources/item.fields.name'))->searchable(),
            LensColumn::text('supplier.name_ar', __('filament/resources/item.fields.supplier_id'))->searchable(),
            LensColumn::text('available_quantity', __('filament/resources/item.fields.available_quantity'))->numeric()->sortable(),
            LensColumn::text('sale_price', __('filament/resources/item.fields.sale_price'))->numeric()->sortable(),
        ];
    }
}
