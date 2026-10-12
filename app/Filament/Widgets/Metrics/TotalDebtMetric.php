<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\TotalDebt;
use App\Metrics\Currency;
use App\Metrics\MetricRange;

/**
 * The total remaining debt to the suppliers in Chinese yuan. It has only one range, so no range list is shown.
 */
class TotalDebtMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.total_debt');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.total_debt.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.total_debt.example');
    }

    protected function calculation(): Calculation
    {
        return new TotalDebt;
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
        return Currency::ChineseYuan;
    }
}
