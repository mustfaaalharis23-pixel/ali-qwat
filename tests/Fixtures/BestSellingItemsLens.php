<?php

namespace Tests\Fixtures;

use App\Filament\Lenses\Lens;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

/**
 * EN: A sample lens used only by the tests of the Lens base class, so no real lens needs its own tests.
 * AR: عدسة وهمية بس لتستات القالب Lens، حتى ما تحتاج كل عدسة حقيقية تستات خاصة بيها.
 */
class BestSellingItemsLens extends Lens
{
    public static function uriKey(): string
    {
        return 'best-selling-items';
    }

    public static function label(): string
    {
        return 'Best selling items';
    }

    public static function explanation(): string
    {
        return 'The items sold the most.';
    }

    public static function guidance(): string
    {
        return 'Keep them in stock.';
    }

    public function query(): Builder
    {
        return Item::query();
    }

    public function columns(): array
    {
        return [];
    }
}
