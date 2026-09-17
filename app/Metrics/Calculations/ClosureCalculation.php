<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use Closure;

/**
 * A calculation that calls a function, for a card whose number already comes from a model function,
 * such as the totals of the supplier shown on its page. The function receives the period.
 */
class ClosureCalculation implements Calculation
{
    /**
     * @param  Closure(Period): (float|int)  $calculate
     */
    public function __construct(private Closure $calculate) {}

    public function calculate(Period $period): float
    {
        return (float) ($this->calculate)($period);
    }
}
