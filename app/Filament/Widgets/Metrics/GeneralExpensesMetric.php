<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\GeneralExpensesTotal;
use App\Metrics\Currency;
use App\Metrics\MetricRange;

/**
 * The general expenses in Iraqi dinar, without the purchase invoices, with every range to choose from. It opens on this month.
 */
class GeneralExpensesMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.general_expenses');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.general_expenses.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.general_expenses.example');
    }

    protected function calculation(): Calculation
    {
        return new GeneralExpensesTotal;
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
