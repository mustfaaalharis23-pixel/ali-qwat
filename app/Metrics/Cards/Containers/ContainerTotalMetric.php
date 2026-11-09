<?php

namespace App\Metrics\Cards\Containers;

use App\Metrics\Cards\ValueMetric;
use App\Metrics\Currency;
use App\Metrics\MetricRange;
use App\Models\Container;

/**
 * The template of the total cards on a container's page: the container comes from the page,
 * the numbers are overall totals so there is no range list, and amounts are in US dollars.
 */
abstract class ContainerTotalMetric extends ValueMetric
{
    /**
     * The container of the page, set by the controller that shows the card.
     */
    public ?Container $record = null;

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
        return Currency::UsDollar;
    }
}
