<?php

namespace App\Filament\Resources\Suppliers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The supplier's total debt, from Supplier::totalDebt().
 */
class SupplierDebtMetric extends SupplierTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/supplier.widgets.totals.total_debt');
    }

    protected function explanation(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.total_debt.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.total_debt.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->totalDebt());
    }
}
