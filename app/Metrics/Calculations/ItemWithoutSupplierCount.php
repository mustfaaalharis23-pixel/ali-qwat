<?php

namespace App\Metrics\Calculations;

use App\Metrics\Calculation;
use App\Metrics\Period;
use App\Models\Item;

/**
 * The number of active items without a supplier. Inactive and deleted items are not counted, and the period is not used.
 */
class ItemWithoutSupplierCount implements Calculation
{
    public function calculate(Period $period): float
    {
        return (float) Item::query()->active()->whereNull('supplier_id')->count();
    }
}
