<?php

namespace App\Filament\Resources\Customers\Widgets;

use Carbon\CarbonInterface;

/**
 * The days since the customer's last order, from Customer::daysSinceLastOrder(), with the date of that order beside them.
 */
class CustomerLastOrderMetric extends CustomerDaysSinceMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.last_order');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.last_order.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.last_order.example');
    }

    protected function lastDate(): ?CarbonInterface
    {
        return $this->record->lastOrderDate();
    }

    protected function daysSince(): ?int
    {
        return $this->record->daysSinceLastOrder();
    }
}
