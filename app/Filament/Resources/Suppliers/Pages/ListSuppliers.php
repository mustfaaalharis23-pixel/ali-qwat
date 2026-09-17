<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Suppliers\Widgets\SupplierDebtsChart;
use App\Filament\Widgets\Metrics\TotalDebtMetric;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * The total debt card and the pie of the debt to each supplier, side by side above the table.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            TotalDebtMetric::class,
            SupplierDebtsChart::class,
        ];
    }
}
