<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ItemWithoutSupplierCount;
use App\Metrics\MetricRange;

/**
 * The number of items without a supplier. It has only one range, so no range list is shown.
 */
class ItemWithoutSupplierCountMetric extends ValueMetric
{
    protected function label(): string
    {
        return __('metrics.cards.item_without_supplier_count');
    }

    protected function explanation(): string
    {
        return __('metrics.cards_info.item_without_supplier_count.explanation');
    }

    protected function example(): string
    {
        return __('metrics.cards_info.item_without_supplier_count.example');
    }

    protected function calculation(): Calculation
    {
        return new ItemWithoutSupplierCount;
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
