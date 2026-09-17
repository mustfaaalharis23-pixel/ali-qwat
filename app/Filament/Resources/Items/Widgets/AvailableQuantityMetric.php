<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;

/**
 * What the item has in the stock right now.
 */
class AvailableQuantityMetric extends ItemMetric
{
    protected function label(): string
    {
        return __('filament/resources/item.widgets.available_quantity');
    }

    protected function explanation(): string
    {
        return __('filament/resources/item.widgets_info.available_quantity.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/item.widgets_info.available_quantity.example');
    }

    protected function numberOf(Item $item): float
    {
        return (float) $item->available_quantity;
    }
}
