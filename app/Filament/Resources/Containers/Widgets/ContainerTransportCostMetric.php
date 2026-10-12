<?php

namespace App\Filament\Resources\Containers\Widgets;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;

/**
 * The container's transport cost in US dollars, from Container::transportCost().
 */
class ContainerTransportCostMetric extends ContainerTotalMetric
{
    protected function label(): string
    {
        return __('filament/resources/container.widgets.totals.transport_cost');
    }

    protected function explanation(): string
    {
        return __('filament/resources/container.widgets.totals_info.transport_cost.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/container.widgets.totals_info.transport_cost.example');
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record->transportCost());
    }
}
