<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Item;

/**
 * The number of items, active or not. Deleted items are not counted, and the period is not used.
 */
class ItemCount implements Calculation
{
    public function calculate(Period $period): float
    {
        return (float) Item::query()->count();
    }
}
