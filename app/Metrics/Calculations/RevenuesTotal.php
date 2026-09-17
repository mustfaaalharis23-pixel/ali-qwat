<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Invoice;

/**
 * The sum of what was paid on the revenues dated inside the period. What a revenue is comes from Invoice::revenues().
 */
class RevenuesTotal implements Calculation
{
    public function calculate(Period $period): float
    {
        return (float) $period->applyTo(Invoice::query()->revenues(), 'date')->sum('paid_amount');
    }
}
