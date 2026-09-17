<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\Currency;

/**
 * The number of the customer's orders, from Customer::ordersCount(). It is a count, so it has no currency.
 */
class CustomerOrdersCountMetric extends CustomerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.orders_count');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.orders_count.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.orders_count.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): int => $this->record->ordersCount());
    }

    protected function currency(): ?Currency
    {
        return null;
    }
}
