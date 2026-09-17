<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The customer's average order value, from Customer::averageOrderValue().
 */
class CustomerAverageOrderMetric extends CustomerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.average_order');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.average_order.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.average_order.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->averageOrderValue());
    }
}
