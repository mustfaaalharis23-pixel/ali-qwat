<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\Actions\AdvanceStageAction;
use App\Filament\Resources\PurchaseInvoices\Actions\ConfirmInvoiceAction;
use App\Filament\Resources\PurchaseInvoices\Actions\PrintLabelAction;
use App\Filament\Resources\PurchaseInvoices\Actions\PrintPurchaseInvoiceAction;
use App\Filament\Resources\PurchaseInvoices\Actions\RevertConfirmationAction;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    /**
     * The page uses the full width, so the lines and payoffs tables have room.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * The invoice's name alone, so the heading keeps its room next to the actions.
     */
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    /**
     * The invoice's next steps stay as buttons; printing and taking back a confirmation sit in the "More" menu,
     * so the header stays on one line and the heading is not squeezed.
     */
    protected function getHeaderActions(): array
    {
        return [
            AdvanceStageAction::make(),
            ConfirmInvoiceAction::make(),
            EditAction::make(),
            ActionGroup::make([
                PrintPurchaseInvoiceAction::make(),
                PrintLabelAction::make(),
                RevertConfirmationAction::make(),
            ])
                ->label(__('filament/resources/purchase_invoice.actions.more'))
                ->icon(Heroicon::EllipsisVertical)
                ->color('gray')
                ->button(),
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
