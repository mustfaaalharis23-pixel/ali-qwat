<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;

/**
 * What was bought of the item and did not arrive yet.
 */
class IncomingQuantityMetric extends ItemMetric
{
    protected function label(): string
    {
        return __('filament/resources/item.widgets.incoming_quantity');
    }

    protected function explanation(): string
    {
        return __('filament/resources/item.widgets_info.incoming_quantity.explanation');
    }

    protected function example(): string
    {
        return __('filament/resources/item.widgets_info.incoming_quantity.example');
    }

    protected function numberOf(Item $item): float
    {
        return (float) $item->incoming_quantity;
    }
}
