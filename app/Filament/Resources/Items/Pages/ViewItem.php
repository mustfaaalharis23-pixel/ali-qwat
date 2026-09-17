<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Items\Widgets\AvailableQuantityMetric;
use App\Filament\Resources\Items\Widgets\AveragePurchasePriceMetric;
use App\Filament\Resources\Items\Widgets\HighestPurchasePriceMetric;
use App\Filament\Resources\Items\Widgets\IncomingQuantityMetric;
use App\Filament\Resources\Items\Widgets\LastPurchasePriceMetric;
use App\Filament\Resources\Items\Widgets\LowestPurchasePriceMetric;
use App\Filament\Resources\Items\Widgets\ProfitMarginMetric;
use App\Filament\Resources\Items\Widgets\SoldQuantityMetric;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItem extends ViewRecord
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * The item's numbers above its details: what it has in the stock, what is on its way, what was sold of it,
     * the prices it was bought at, and what it earns on one unit. The price cards and the profit margin show only
     * to a user who can see what the items cost us.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AvailableQuantityMetric::class,
            IncomingQuantityMetric::class,
            SoldQuantityMetric::class,
            LastPurchasePriceMetric::class,
            AveragePurchasePriceMetric::class,
            HighestPurchasePriceMetric::class,
            LowestPurchasePriceMetric::class,
            ProfitMarginMetric::class,
        ];
    }

    /**
     * One card per row on small screens, two on medium screens, and four on large screens.
     *
     * @return array<string, int>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
