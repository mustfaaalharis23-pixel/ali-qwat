<?php

namespace App\Filament\Resources\Suppliers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The supplier's total purchases, from Supplier::totalPurchases().
 */
class SupplierPurchasesMetric extends SupplierTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/supplier.widgets.totals.total_purchases');
    }

    protected function explanation(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.total_purchases.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.total_purchases.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->totalPurchases());
    }
}
