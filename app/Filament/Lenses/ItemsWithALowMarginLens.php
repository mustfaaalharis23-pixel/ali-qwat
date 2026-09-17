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
 * The active items that earn something on a sale, but less than the lowest margin the factory accepts:
 * the margin is what is left of the sale price after the cost, out of the sale price itself.
 * An item sold at a loss is not here — it has its own lens, so one item never rings two alarms.
 * Each row has an action to fix the price. The lens shows what the items cost us,
 * so it needs the item.view_cost_price permission as well as its own.
 */
class ItemsWithALowMarginLens extends Lens
{
    /**
     * The lowest margin the factory accepts on one unit, as a percentage of the sale price.
     */
    private const LOWEST_MARGIN_PERCENTAGE = 15;

    public static function uriKey(): string
    {
        return 'items-with-a-low-margin';
    }

    public static function label(): string
    {
        return __('filament/lenses.items_with_a_low_margin.label', ['percentage' => self::LOWEST_MARGIN_PERCENTAGE]);
    }

    public static function explanation(): string
    {
        return __('filament/lenses.items_with_a_low_margin.explanation', ['percentage' => self::LOWEST_MARGIN_PERCENTAGE]);
    }

    public static function guidance(): string
    {
        return __('filament/lenses.items_with_a_low_margin.guidance', ['percentage' => self::LOWEST_MARGIN_PERCENTAGE]);
    }

    /**
     * The lens permission is not enough on its own: the lens is built on the cost price, so it opens only
     * to a user who is allowed to see what the items cost us.
     */
    public static function canAccess(): bool
    {
        return parent::canAccess() && Gate::allows('viewCostPrice', Item::class);
    }

    /**
     * The items priced over their cost, whose margin is still under the lowest accepted percentage of the sale price.
     * An item without a sale price is not priced yet, so it is not here either.
     */
    public function query(): Builder
    {
        return Item::query()
            ->active()
            ->where('sale_price', '>', 0)
            ->whereColumn('sale_price', '>=', 'cost_price')
            ->whereRaw('(sale_price - cost_price) < (sale_price * ?)', [self::LOWEST_MARGIN_PERCENTAGE / 100]);
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
                ->numeric(),
            TextColumn::make('profit_margin_percentage')
                ->label(__('filament/resources/item.fields.profit_margin_percentage'))
                ->state(fn (Item $record): string => $this->marginPercentageOf($record).'%')
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

    /**
     * What the item's margin is worth out of its sale price, in percent, rounded to one decimal.
     */
    private function marginPercentageOf(Item $item): float
    {
        $salePrice = (float) $item->sale_price;

        return $salePrice <= 0 ? 0.0 : round($item->profitMargin() / $salePrice * 100, 1);
    }
}
