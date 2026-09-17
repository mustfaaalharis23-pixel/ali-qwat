<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ItemCount;
use App\Metrics\MetricRange;

/**
 * The total number of items. It has only one range, so no range list is shown.
 */
class ItemCountMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.item_count');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.item_count.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.item_count.example');
    }

    protected function calculation(): Calculation
    {
        return new ItemCount;
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
