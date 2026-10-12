<?php

namespace App\Filament\Resources\GeneralInvoices\Pages;

use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
use App\Filament\Resources\GeneralInvoices\Widgets\GeneralExpensesChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneralInvoices extends ListRecords
{
    protected static string $resource = GeneralInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * The pie of where the general expenses went, above the table.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            GeneralExpensesChart::class,
        ];
    }
}
