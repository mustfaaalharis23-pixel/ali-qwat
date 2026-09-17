<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The customer's total sales, from Customer::totalSales().
 */
class CustomerSalesMetric extends CustomerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/customer.widgets.totals.total_sales');
    }

    protected function explanation(): string
    {
        return __('filament/resources/customer.widgets.totals_info.total_sales.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/customer.widgets.totals_info.total_sales.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->totalSales());
    }
}
