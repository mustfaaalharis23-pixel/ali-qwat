<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;

/**
 * What one unit of the item cost on the last purchase.
 */
class LastPurchasePriceMetric extends ItemPurchasePriceMetric
{
    protected function label(): string
    {
        return __('filament/resources/item.widgets.last_purchase_price');
    }

    protected function explanation(): string
    {
        return __('filament/resources/item.widgets_info.last_purchase_price.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/item.widgets_info.last_purchase_price.example');
    }

    protected function numberOf(Item $item): float
    {
        return (float) $item->cost_price;
    }
}
