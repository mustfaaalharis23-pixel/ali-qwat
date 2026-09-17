<?php

namespace App\Filament\Lenses;

use App\Filament\Resources\Items\Actions\SetSalePriceAction;
use App\Filament\Tables\Columns\IdColumn;
use App\Models\Item;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The active items sold at a loss: their sale price is under what one unit of them last cost, so every unit sold
 * loses the difference. Each row has an action to fix the price, either by writing the sale price
 * or by writing the profit margin wanted, and the other one is worked out from the cost.
 * The lens shows what the items cost us, so it needs the item.view_cost_price permission as well as its own.
 */
class ItemsSoldAtALossLens extends Lens
{
    public static function uriKey(): string
    {
        return 'items-sold-at-a-loss';
    }

    public static function label(): string
    {
        return __('filament/lenses.items_sold_at_a_loss.label');
    }

    public static function explanation(): string
    {
        return __('filament/lenses.items_sold_at_a_loss.explanation');
    }

    public static function guidance(): string
    {
        return __('filament/lenses.items_sold_at_a_loss.guidance');
    }

    /**
     * The lens permission is not enough on its own: the lens is built on the cost price, so it opens only
     * to a user who is allowed to see what the items cost us.
     */
    public static function canAccess(): bool
    {
        return parent::canAccess() && Gate::allows('viewCostPrice', Item::class);
    }

    public function query(): Builder
    {
        return Item::query()
            ->active()
            ->whereColumn('sale_price', '<', 'cost_price');
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
            TextColumn::make('sale_price')
                ->label(__('filament/resources/item.fields.sale_price'))
                ->numeric()
                ->sortable(),
            TextColumn::make('cost_price')
                ->label(__('filament/resources/item.fields.cost_price'))
                ->numeric()
                ->sortable(),
            TextColumn::make('profit_margin')
                ->label(__('filament/resources/item.fields.profit_margin'))
                ->state(fn (Item $record): float => $record->profitMargin())
                ->numeric()
                ->color('danger'),
        ];
    }

    /**
     * Fix the price of the item: the sale price, the profit margin, or the margin as a percentage of the sale price.
     */
    public function recordActions(): array
    {
        return [
            SetSalePriceAction::make(),
        ];
    }
}
