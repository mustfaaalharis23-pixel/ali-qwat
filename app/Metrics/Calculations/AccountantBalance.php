<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Invoice;

/**
 * The accountant's box for its card. The formula comes from Invoice::accountantBalance().
 */
class AccountantBalance implements Calculation
{
    public function calculate(Period $period): float
    {
        return Invoice::accountantBalance($period);
    }
}
