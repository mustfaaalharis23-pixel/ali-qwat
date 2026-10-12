<?php

namespace App\Filament\Resources\InvoiceCategories\Pages;

use App\Filament\Resources\InvoiceCategories\InvoiceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInvoiceCategories extends ManageRecords
{
    protected static string $resource = InvoiceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
