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
            TextColumn::make('sale_price')
                ->label(__('filament/resources/item.fields.sale_price'))
                ->numeric()
                ->sortable(),
        ];
    }

    /**
     * Activate the item, so its quantity can be sold again. Only a user who can edit items sees it.
     */
    public function recordActions(): array
    {
        return [
            Action::make('activate')
                ->label(__('filament/resources/item.actions.activate.label'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading(fn (Item $record): string => __('filament/resources/item.actions.activate.heading', ['item' => $record->name]))
                ->modalDescription(__('filament/resources/item.actions.activate.description'))
                ->action(function (Item $record, Action $action): void {
                    $record->update(['is_active' => true]);

                    $action->success();
                })
                ->successNotificationTitle(__('filament/resources/item.actions.activate.saved')),
        ];
    }
}
