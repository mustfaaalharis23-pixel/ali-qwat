<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The customer's total paid, from Customer::totalPaid().
 */
class CustomerPaidMetric extends CustomerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.total_paid');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.total_paid.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.total_paid.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->totalPaid());
    }
}
