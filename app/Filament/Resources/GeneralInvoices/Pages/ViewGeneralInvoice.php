<?php

namespace App\Filament\Resources\GeneralInvoices\Pages;

use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
use App\Models\Invoice;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGeneralInvoice extends ViewRecord
{
    protected static string $resource = GeneralInvoiceResource::class;

    /**
     * A money transfer invoice has no edit button, because it is read only.
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn (Invoice $record): bool => $record->isMoneyTransfer()),
        ];
    }
}
