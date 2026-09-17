<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ActiveItemCount;
use App\Metrics\MetricRange;

/**
 * The number of active items. It has only one range, so no range list is shown.
 */
class ActiveItemCountMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.active_item_count');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.active_item_count.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.active_item_count.example');
    }

    protected function calculation(): Calculation
    {
        return new ActiveItemCount;
    }

    protected function ranges(): array
    {
        return [MetricRange::All];
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }
}
