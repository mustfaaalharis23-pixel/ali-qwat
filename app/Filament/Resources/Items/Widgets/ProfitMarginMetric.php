<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;

/**
 * What the factory earns on one unit of the item: its sale price minus its cost price.
 */
class ProfitMarginMetric extends ItemPurchasePriceMetric
{
    protected function label(): string
    {
        return __('filament/resources/item.widgets.profit_margin');
    }

    protected function explanation(): string
    {
        return __('filament/resources/item.widgets_info.profit_margin.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/item.widgets_info.profit_margin.example');
    }

    protected function numberOf(Item $item): float
    {
        return $item->profitMargin();
    }
}
