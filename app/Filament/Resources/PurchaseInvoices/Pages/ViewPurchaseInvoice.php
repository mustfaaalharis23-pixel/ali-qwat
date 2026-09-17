<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\Actions\ConfirmInvoiceAction;
use App\Filament\Resources\PurchaseInvoices\Actions\RevertConfirmationAction;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Livewire\Attributes\On;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    /**
     * The page uses the full width, so the lines and payoffs tables have room.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            ConfirmInvoiceAction::make(),
            RevertConfirmationAction::make(),
            EditAction::make(),
        ];
    }

    /**
     * Reloads the invoice after its payoffs change, so the paid amount and remaining on the page are up to date.
     */
    #[On('payoffs-changed')]
    public function refreshInvoice(): void
    {
        $this->record->refresh();
    }
}
