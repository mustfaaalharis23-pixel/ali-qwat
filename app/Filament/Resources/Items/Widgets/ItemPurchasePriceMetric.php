<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Metrics\Currency;
use App\Models\Item;

/**
 * The template of the purchase price cards on an item's page: amounts in Iraqi dinars (the final cost of a piece), shown only to a user
 * who has the item.view_cost_price permission, because they say what the item costs us.
 */
abstract class ItemPurchasePriceMetric extends ItemMetric
{
    public static function canView(): bool
    {
        return auth()->user()?->can('viewCostPrice', Item::class) ?? false;
    }

    protected function currency(): ?Currency
    {
        return Currency::IraqiDinar;
    }
}
