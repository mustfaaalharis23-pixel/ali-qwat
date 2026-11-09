<?php

namespace App\Lenses;

use App\Metrics\Cards\ItemWithoutSupplierCountMetric;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

/**
 * The active items without a supplier, with their count card above the table. Deleted items are not shown.
 * Each row has an action to choose the item's supplier, and the selected rows can be given one supplier at once;
 * once chosen, the item leaves the lens.
 */
class ItemsWithoutSupplierLens extends Lens
{
    public static function uriKey(): string
    {
        return 'items-without-supplier';
    }

    public static function label(): string
    {
        return __('filament/lenses.items_without_supplier.label');
    }

    public static function explanation(): string
    {
        return __('filament/lenses.items_without_supplier.explanation');
    }

    public static function guidance(): string
    {
        return __('filament/lenses.items_without_supplier.guidance');
    }

    public function query(): Builder
    {
        return Item::query()->active()->whereNull('supplier_id');
    }

    public function columns(): array
    {
        return [
            LensColumn::id(),
            LensColumn::image('image', __('filament/resources/item.fields.image')),
            LensColumn::text('name', __('filament/resources/item.fields.name'))->searchable(),
            LensColumn::text('sale_price', __('filament/resources/item.fields.sale_price'))->numeric()->sortable(),
            LensColumn::text('cost_price', __('filament/resources/item.fields.cost_price'))->numeric()->sortable(),
            LensColumn::boolean('is_active', __('filament/resources/item.fields.is_active')),
        ];
    }

    /**
     * Choose the supplier of the item. Only a user who can edit items gets it.
     */
    public function recordActions(): array
    {
        return ['setSupplier'];
    }

    /**
     * Choose one supplier for all the selected items at once. Only a user who can edit items gets it.
     */
    public function bulkActions(): array
    {
        return ['setSupplierForSelected'];
    }

    public function metrics(): array
    {
        return [ItemWithoutSupplierCountMetric::class];
    }
}
