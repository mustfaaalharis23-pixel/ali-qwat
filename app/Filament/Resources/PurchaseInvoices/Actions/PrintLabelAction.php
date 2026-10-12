<?php

namespace App\Filament\Resources\PurchaseInvoices\Actions;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Downloads the carton label of a purchase invoice as a PDF, to send to the supplier's factory.
 */
class PrintLabelAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'printLabel';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament/resources/purchase_invoice.actions.print_label'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->url(fn (Invoice $record): string => route('purchase-invoices.label', $record))
            ->openUrlInNewTab();
    }
}
