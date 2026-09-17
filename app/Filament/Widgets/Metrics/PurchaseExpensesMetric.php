<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\PurchaseExpensesTotal;
use App\Metrics\Currency;
use App\Metrics\MetricRange;

/**
 * The purchase expenses in Iraqi dinar: what was paid on the purchase invoices, with every range to choose from. It opens on this month.
 */
class PurchaseExpensesMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.purchase_expenses');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.purchase_expenses.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.purchase_expenses.example');
    }

    protected function calculation(): Calculation
    {
        return new PurchaseExpensesTotal;
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
