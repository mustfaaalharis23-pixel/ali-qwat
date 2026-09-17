<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Invoice;

/**
 * The total debt for its card. The debt is what remains now, so the period is not used. It comes from Invoice::totalDebt().
 */
class TotalDebt implements Calculation
{
    public function calculate(Period $period): float
    {
        return Invoice::totalDebt();
    }
}
