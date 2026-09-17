<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;

/**
 * Everything sold of the item.
 */
class SoldQuantityMetric extends ItemMetric
{
    protected function label(): string
    {
        return __('filament/resources/item.widgets.sold_quantity');
    }

    protected function explanation(): string
    {
        return __('filament/resources/item.widgets_info.sold_quantity.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/item.widgets_info.sold_quantity.example');
    }

    protected function numberOf(Item $item): float
    {
        return $item->soldQuantity();
    }
}
