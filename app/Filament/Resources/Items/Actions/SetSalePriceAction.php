<?php

namespace App\Filament\Resources\Items\Actions;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * Sets the sale price of one item, in the way that suits the user: the sale price itself, the profit margin wanted
 * on one unit, or the margin wanted as a percentage of the sale price. Writing any one of the three works out
 * the other two from the cost price, and only the sale price is saved.
 * It shows only to users who can edit items.
 */
class SetSalePriceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'setSalePrice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament/resources/item.actions.set_sale_price.label'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->authorize('update')
            ->modalHeading(fn (Item $record): string => __('filament/resources/item.actions.set_sale_price.heading', ['item' => $record->name]))
            ->fillForm(fn (Item $record): array => [
                'cost_price' => $this->formatted((float) $record->cost_price),
                'sale_price' => $this->formatted((float) $record->sale_price),
                'profit_margin' => $this->formatted($record->profitMargin()),
                'profit_margin_percentage' => $this->percentage((float) $record->sale_price, (float) $record->cost_price),
            ])
            ->schema([
                MoneyInput::make('cost_price')
                    ->label(__('filament/resources/item.fields.cost_price'))
                    ->helperText(__('filament/resources/item.actions.set_sale_price.cost_hint'))
                    ->disabled()
                    ->dehydrated(false),
                MoneyInput::make('sale_price')
                    ->label(__('filament/resources/item.fields.sale_price'))
                    ->helperText(__('filament/resources/item.actions.set_sale_price.sale_price_hint'))
                    ->minValue(0)
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        $cost = $this->money($get('cost_price'));
                        $sale = $this->money($state);

                        $set('profit_margin', $this->formatted($sale - $cost));
                        $set('profit_margin_percentage', $this->percentage($sale, $cost));
                    }),
                MoneyInput::make('profit_margin')
                    ->label(__('filament/resources/item.fields.profit_margin'))
                    ->helperText(__('filament/resources/item.actions.set_sale_price.profit_margin_hint'))
                    ->dehydrated(false)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        $cost = $this->money($get('cost_price'));
                        $sale = $cost + $this->money($state);

                        $set('sale_price', $this->formatted($sale));
                        $set('profit_margin_percentage', $this->percentage($sale, $cost));
                    }),
                TextInput::make('profit_margin_percentage')
                    ->label(__('filament/resources/item.fields.profit_margin_percentage'))
                    ->helperText(__('filament/resources/item.actions.set_sale_price.profit_margin_percentage_hint'))
                    ->numeric()
                    ->suffix('%')
                    ->maxValue(99)
                    ->dehydrated(false)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        $cost = $this->money($get('cost_price'));
                        $sale = $this->salePriceOfPercentage($cost, (float) $state);

                        $set('sale_price', $this->formatted($sale));
                        $set('profit_margin', $this->formatted($sale - $cost));
                    }),
            ])
            ->action(function (Item $record, array $data, Action $action): void {
                $record->update(['sale_price' => (float) $data['sale_price']]);

                $action->success();
            })
            ->successNotificationTitle(__('filament/resources/item.actions.set_sale_price.saved'));
    }

    /**
     * The sale price that earns the given percentage of itself: the cost over what is left of the price after the margin.
     * A percentage of a hundred or more has no sale price, so the cost is kept.
     */
    private function salePriceOfPercentage(float $costPrice, float $percentage): float
    {
        if ($percentage >= 100) {
            return $costPrice;
        }

        return round($costPrice / (1 - $percentage / 100), 2);
    }

    /**
     * What the margin is worth out of the sale price, in percent. A price of nothing earns nothing.
     */
    private function percentage(float $salePrice, float $costPrice): float
    {
        if ($salePrice <= 0) {
            return 0.0;
        }

        return round(($salePrice - $costPrice) / $salePrice * 100, 2);
    }

    /**
     * The number a money field hands over: it carries the thousands separators, which are not part of the number.
     */
    private function money(mixed $state): float
    {
        return (float) str_replace(',', '', (string) $state);
    }

    /**
     * The number as the money fields show it: thousands separators, and decimals only when the number has them.
     */
    private function formatted(float $value): string
    {
        return number_format($value, $value === floor($value) ? 0 : 2, '.', ',');
    }
}
