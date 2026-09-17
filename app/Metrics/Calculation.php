<?php

namespace App\Metrics;

/**
 * A number a metric card shows. Each calculation gets the period of the chosen range and returns its value.
 */
interface Calculation
{
    /**
     * The value of this calculation within the period.
     */
    public function calculate(Period $period): float;
}
