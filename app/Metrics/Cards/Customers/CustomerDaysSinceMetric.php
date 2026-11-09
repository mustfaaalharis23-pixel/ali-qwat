<?php

namespace App\Metrics\Cards\Customers;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use Carbon\CarbonInterface;

/**
 * The template of a card that shows the days since the customer's last event, such as its last order or payment,
 * with the date of that event beside them. A customer without such an event shows a dash instead of a number.
 */
abstract class CustomerDaysSinceMetric extends CustomerTotalMetric
{
    /**
     * The date of the customer's last event, or null when there is none.
     */
    abstract protected function lastDate(): ?CarbonInterface;

    /**
     * The whole days since the customer's last event, or null when there is none.
     */
    abstract protected function daysSince(): ?int;

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): int => $this->daysSince() ?? 0);
    }

    protected function currency(): ?Currency
    {
        return null;
    }

    /**
     * The days are followed by their unit and the date of the last event, in place of a currency symbol.
     * A customer without such an event shows a dash.
     */
    protected function formattedValue(float $raw): FormattedValue
    {
        $lastDate = $this->lastDate();

        if ($lastDate === null) {
            return new FormattedValue('—', null);
        }

        return new FormattedValue(
            parent::formattedValue($raw)->number,
            __('filament/resources/customer.widgets.since_date', [
                'unit' => trans_choice('filament/resources/customer.widgets.days', $this->daysSince()),
                'date' => $lastDate->format('Y/m/d'),
            ]),
        );
    }
}
