<?php

namespace App\Filament\Lenses;

use App\Filament\Tables\Columns\IdColumn;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
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

    public function query(): Builder
    {
        return Item::query()
            ->active()
            ->where('available_quantity', '<', 0);
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
            TextColumn::make('available_quantity')
                ->label(__('filament/resources/item.fields.available_quantity'))
                ->numeric()
                ->color('danger')
                ->sortable(),
            TextColumn::make('incoming_quantity')
                ->label(__('filament/resources/item.fields.incoming_quantity'))
                ->numeric()
                ->color('gray')
                ->sortable(),
            TextColumn::make('sold_quantity')
                ->label(__('filament/resources/item.fields.sold_quantity'))
                ->state(fn (Item $record): float => $record->soldQuantity())
                ->numeric(),
        ];
    }

    /**
     * Works the item's quantities and cost out again, from the purchases and the orders themselves.
     * A number left over from an older write is fixed here; a quantity that is really negative stays,
     * and its purchase invoice is what needs the entry. Only a user who can edit items sees it.
     */
    public function recordActions(): array
    {
        return [
            Action::make('recalculateStock')
                ->label(__('filament/resources/item.actions.recalculate_stock.label'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading(fn (Item $record): string => __('filament/resources/item.actions.recalculate_stock.heading', ['item' => $record->name]))
                ->modalDescription(__('filament/resources/item.actions.recalculate_stock.description'))
                ->action(function (Item $record, Action $action): void {
                    $record->recalculateStock();

                    $action->success();
                })
                ->successNotificationTitle(__('filament/resources/item.actions.recalculate_stock.saved')),
        ];
    }
}
