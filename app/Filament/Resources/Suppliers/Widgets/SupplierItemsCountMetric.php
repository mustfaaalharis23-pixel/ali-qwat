<?php

namespace App\Filament\Resources\Suppliers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\Currency;

/**
 * The number of the supplier's items, from Supplier::itemsCount(). It is a count, so it has no currency.
 */
class SupplierItemsCountMetric extends SupplierTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/supplier.widgets.totals.items_count');
    }

    protected function explanation(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.items_count.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/supplier.widgets.totals_info.items_count.example');
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
