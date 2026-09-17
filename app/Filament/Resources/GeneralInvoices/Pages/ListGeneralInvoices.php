<?php

namespace App\Filament\Resources\GeneralInvoices\Pages;

use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
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
}
