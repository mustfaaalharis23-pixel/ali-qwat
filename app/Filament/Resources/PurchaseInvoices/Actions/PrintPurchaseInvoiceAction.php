<?php

namespace App\Filament\Resources\PurchaseInvoices\Actions;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Opens the checking sheet of a purchase invoice in a new tab, ready to print for the employee who receives the goods.
 */
class PrintPurchaseInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'printPurchaseInvoice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament/resources/purchase_invoice.actions.print'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (Invoice $record): string => route('purchase-invoices.print', ['invoice' => $record]))
            ->openUrlInNewTab();
    }
}
