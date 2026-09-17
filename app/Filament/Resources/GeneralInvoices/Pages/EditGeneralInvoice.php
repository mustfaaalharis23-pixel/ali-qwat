<?php

namespace App\Filament\Resources\GeneralInvoices\Pages;

use App\Filament\Resources\GeneralInvoices\GeneralInvoiceResource;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralInvoice extends EditRecord
{
    protected static string $resource = GeneralInvoiceResource::class;

    /**
     * A money transfer invoice is read only, so opening its edit page shows its view page instead.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        if ($invoice->isMoneyTransfer()) {
            $this->redirect(GeneralInvoiceResource::getUrl('view', ['record' => $invoice]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make()
                ->superOnly(),
        ];
    }
}
