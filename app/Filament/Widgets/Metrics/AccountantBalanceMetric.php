<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\AccountantBalance;
use App\Metrics\Currency;
use App\Metrics\MetricRange;

/**
 * The accountant's box in Iraqi dinar. It has only one range, all, so no range list is shown.
 */
class AccountantBalanceMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.accountant_balance');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.accountant_balance.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.accountant_balance.example');
    }

    protected function calculation(): Calculation
    {
        return new AccountantBalance;
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
