<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The customer's remaining debt, from Customer::debt().
 */
class CustomerDebtMetric extends CustomerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.debt');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.debt.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.debt.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->debt());
    }
}
