<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\RevenuesTotal;
use App\Metrics\Currency;
use App\Metrics\MetricRange;

/**
 * The revenues in Iraqi dinar, with every range to choose from. It opens on this month.
 */
class RevenuesMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.revenues');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.revenues.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.revenues.example');
    }

    protected function calculation(): Calculation
    {
        return new RevenuesTotal;
    }

    protected function ranges(): array
    {
        return MetricRange::cases();
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::ThisMonth;
    }

    protected function currency(): ?Currency
    {
        return Currency::IraqiDinar;
    }
}
