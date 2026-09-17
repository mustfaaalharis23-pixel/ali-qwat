<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;

/**
 * What one unit of the item cost on average.
 */
class AveragePurchasePriceMetric extends ItemPurchasePriceMetric
{
    protected function label(): string
    {
        return __('filament/resources/item.widgets.average_purchase_price');
    }

    protected function explanation(): string
    {
        return __('filament/resources/item.widgets_info.average_purchase_price.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/item.widgets_info.average_purchase_price.example');
    }

    protected function numberOf(Item $item): float
    {
        return $item->averagePurchasePrice();
    }
}
