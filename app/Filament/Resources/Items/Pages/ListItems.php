<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Widgets\Metrics\ActiveItemCountMetric;
use App\Filament\Widgets\Metrics\InactiveItemCountMetric;
use App\Filament\Widgets\Metrics\ItemCountMetric;
use App\Filament\Widgets\Metrics\ItemWithoutSupplierCountMetric;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * The item count cards above the table: total, active, inactive, and without a supplier.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ItemCountMetric::class,
            ActiveItemCountMetric::class,
            InactiveItemCountMetric::class,
            ItemWithoutSupplierCountMetric::class,
        ];
    }

    /**
     * One column on small screens, two on medium screens, and four on large screens.
     *
     * @return array<string, int>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
