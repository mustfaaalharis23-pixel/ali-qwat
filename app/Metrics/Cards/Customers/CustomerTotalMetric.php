<?php

namespace App\Metrics\Cards\Customers;

use App\Metrics\Cards\ValueMetric;
use App\Metrics\Currency;
use App\Metrics\MetricRange;
use App\Models\Customer;

/**
 * The template of the total cards on a customer's page: the customer comes from the page,
 * the numbers are overall totals so there is no range list, and amounts are in Iraqi dinar.
 */
abstract class CustomerTotalMetric extends ValueMetric
{
    /**
     * The customer of the page, set by the controller that shows the card.
     */
    public ?Customer $record = null;

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
        return Currency::IraqiDinar;
    }
}
