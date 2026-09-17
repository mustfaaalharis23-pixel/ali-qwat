<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\StockValue;
use App\Metrics\Currency;
use App\Metrics\MetricRange;
use App\Models\Item;

/**
 * What the stock is worth in Iraqi dinar right now. It says what the items cost us, so it shows only to a user
 * who has the item.view_cost_price permission. It has only one range, so no range list is shown.
 */
class StockValueMetric extends ValueMetric
{
    public static function canView(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }

    protected function label(): string
    {
        return __('metrics.cards.stock_value');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.stock_value.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.stock_value.example');
    }

    protected function calculation(): Calculation
    {
        return new StockValue;
    }

    protected function ranges(): array
    {
        return [MetricRange::All];
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }

    protected function currency(): ?Currency
    {
        return Currency::IraqiDinar;
    }
}
