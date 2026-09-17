<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Invoice;

/**
 * The number of invoices dated inside the period. Deleted invoices are not counted.
 */
class InvoiceCount implements Calculation
{
    public function calculate(Period $period): float
    {
        return (float) $period->applyTo(Invoice::query(), 'date')->count();
    }
}
