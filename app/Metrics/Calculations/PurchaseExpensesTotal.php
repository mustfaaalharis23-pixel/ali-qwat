<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Invoice;

/**
 * The sum of what was paid on the purchase expenses dated inside the period. What a purchase expense is comes from Invoice::purchaseExpenses().
 */
class PurchaseExpensesTotal implements Calculation
{
    public function calculate(Period $period): float
    {
        return (float) $period->applyTo(Invoice::query()->purchaseExpenses(), 'date')->sum('paid_amount');
    }
}
