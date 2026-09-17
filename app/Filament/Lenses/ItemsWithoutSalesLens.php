<?php

namespace App\Filament\Lenses;

use App\Filament\Tables\Columns\IdColumn;
use App\Models\Item;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
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
            IdColumn::make('id'),
            ImageColumn::make('image')
                ->label(__('filament/resources/item.fields.image'))
                ->disk('public'),
            TextColumn::make('name')
                ->label(__('filament/resources/item.fields.name'))
                ->searchable(),
            TextColumn::make('supplier.name_ar')
                ->label(__('filament/resources/item.fields.supplier_id'))
                ->placeholder('—')
                ->searchable(),
            TextColumn::make('available_quantity')
                ->label(__('filament/resources/item.fields.available_quantity'))
                ->numeric()
                ->sortable(),
            TextColumn::make('sale_price')
                ->label(__('filament/resources/item.fields.sale_price'))
                ->numeric()
                ->sortable(),
        ];
    }
}
