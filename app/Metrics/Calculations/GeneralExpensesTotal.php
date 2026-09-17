<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Invoice;

/**
 * The sum of what was paid on the general expenses dated inside the period. What a general expense is comes from Invoice::generalExpenses().
 */
class GeneralExpensesTotal implements Calculation
{
    public function calculate(Period $period): float
    {
        return (float) $period->applyTo(Invoice::query()->generalExpenses(), 'date')->sum('paid_amount');
    }
}
