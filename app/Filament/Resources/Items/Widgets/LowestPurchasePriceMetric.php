<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;

/**
 * The least one unit of the item was ever bought for.
 */
class LowestPurchasePriceMetric extends ItemPurchasePriceMetric
{
    protected function label(): string
    {
        return __('filament/resources/item.widgets.lowest_purchase_price');
    }

    protected function explanation(): string
    {
        return __('filament/resources/item.widgets_info.lowest_purchase_price.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/item.widgets_info.lowest_purchase_price.example');
    }

    protected function numberOf(Item $item): float
    {
        return $item->lowestPurchasePrice();
    }
}
