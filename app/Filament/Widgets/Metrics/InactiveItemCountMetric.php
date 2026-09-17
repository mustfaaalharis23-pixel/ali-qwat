<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\InactiveItemCount;
use App\Metrics\MetricRange;

/**
 * The number of inactive items. It has only one range, so no range list is shown.
 */
class InactiveItemCountMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.inactive_item_count');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.inactive_item_count.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.inactive_item_count.example');
    }

    protected function calculation(): Calculation
    {
        return new InactiveItemCount;
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
