<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Item;

/**
 * The value of the stock for its card. It is what the stock is worth now, so the period is not used.
 * It comes from Item::stockValue().
 */
class StockValue implements Calculation
{
    public function calculate(Period $period): float
    {
        return Item::stockValue();
    }
}
