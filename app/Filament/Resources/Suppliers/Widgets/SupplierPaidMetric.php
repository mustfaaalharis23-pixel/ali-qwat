<?php

namespace App\Filament\Resources\Suppliers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The supplier's total paid, from Supplier::totalPaid().
 */
class SupplierPaidMetric extends SupplierTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/supplier.widgets.totals.total_paid');
    }

    protected function explanation(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.total_paid.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.total_paid.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->totalPaid());
    }
}
