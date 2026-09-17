<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Suppliers\Widgets\SupplierDebtMetric;
use App\Filament\Resources\Suppliers\Widgets\SupplierItemsCountMetric;
use App\Filament\Resources\Suppliers\Widgets\SupplierPaidMetric;
use App\Filament\Resources\Suppliers\Widgets\SupplierPurchasesMetric;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * The supplier's totals above its details: debt, paid, purchases, and items.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            SupplierDebtMetric::class,
            SupplierPaidMetric::class,
            SupplierPurchasesMetric::class,
            SupplierItemsCountMetric::class,
        ];
    }

    /**
     * One card per row on small screens, two on medium screens, and all four in one row on large screens.
     *
     * @return array<string, int>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return ['md' => 2, 'xl' => 4];
    }
}
