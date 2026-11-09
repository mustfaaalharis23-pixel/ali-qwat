<?php

namespace App\Metrics\Cards\Suppliers;

use App\Metrics\Cards\ValueMetric;
use App\Metrics\Currency;
use App\Metrics\MetricRange;
use App\Models\Supplier;

/**
 * The template of the total cards on a supplier's page: the supplier comes from the page,
 * the numbers are overall totals so there is no range list, and amounts are in Chinese yuan.
 */
abstract class SupplierTotalMetric extends ValueMetric
{
    /**
     * The supplier of the page, set by the controller that shows the card.
     */
    public ?Supplier $record = null;

    protected function ranges(): array
    {
        return [MetricRange::All];
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }

    protected function currency(): ?Currency
    {
        return Currency::ChineseYuan;
    }
}
