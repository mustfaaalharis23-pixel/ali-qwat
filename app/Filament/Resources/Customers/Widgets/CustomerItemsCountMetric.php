<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\Currency;

/**
 * The number of different items the customer bought, from Customer::itemsCount(). It is a count, so it has no currency.
 */
class CustomerItemsCountMetric extends CustomerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.items_count');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.items_count.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.items_count.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): int => $this->record->itemsCount());
    }

    protected function currency(): ?Currency
    {
        return null;
    }
}
