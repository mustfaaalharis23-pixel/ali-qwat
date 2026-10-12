<?php

namespace App\Filament\Resources\Containers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\Currency;

/**
 * The container's CBM, from Container::totalCbm(). It is a volume, so it has no currency.
 */
class ContainerCbmMetric extends ContainerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/container.widgets.totals.total_cbm');
    }

    protected function explanation(): string
    {
        return __('filament/resources/container.widgets.totals_info.total_cbm.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/container.widgets.totals_info.total_cbm.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->totalCbm());
    }

    protected function currency(): ?Currency
    {
        return null;
    }
}
