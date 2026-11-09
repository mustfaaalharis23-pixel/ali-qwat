<?php

namespace App\Metrics\Cards\Items;

use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\Cards\ValueMetric;
use App\Metrics\MetricRange;
use App\Models\Item;

/**
 * The template of the cards on an item's page: the item comes from the page, and the numbers are what the item
 * has right now, so there is no range list.
 */
abstract class ItemMetric extends ValueMetric
{
    /**
     * The item of the page, set by the controller that shows the card.
     */
    public ?Item $record = null;

    /**
     * The number this card shows for the item.
     */
    abstract protected function numberOf(Item $item): float;

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (): float => $this->record === null ? 0.0 : $this->numberOf($this->record));
    }

    protected function ranges(): array
    {
        return [MetricRange::All];
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }
}
