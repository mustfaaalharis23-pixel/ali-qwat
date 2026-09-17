<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseInvoices\Widgets\ImporterConfirmedPurchaseInvoicesMetric;
use App\Filament\Resources\PurchaseInvoices\Widgets\PendingPurchaseInvoicesMetric;
use App\Filament\Resources\PurchaseInvoices\Widgets\SupplierPurchasesChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * The two waiting counts share one row above the table, so they stay small, and the pie of the suppliers
     * we buy from the most gets a row of its own under them: in one row with the pie, the counts stretch to its height.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PendingPurchaseInvoicesMetric::make(['columnSpan' => 1]),
            ImporterConfirmedPurchaseInvoicesMetric::make(['columnSpan' => 1]),
            SupplierPurchasesChart::make(['columnSpan' => 'full']),
        ];
    }

    /**
     * One column on small screens and two from medium screens up.
     *
     * @return array<string, int>
     */
    public function getHeaderWidgetsColumns(): array
    {
        return ['md' => 2];
    }
}
