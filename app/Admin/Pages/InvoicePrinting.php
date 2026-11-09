<?php

namespace App\Admin\Pages;

/**
 * The invoices of the sales, to look one up and print it. Its permission is invoice_printing.view.
 */
class InvoicePrinting extends Page
{
    public static function label(): string
    {
        return __('filament/pages/invoice_printing.title');
    }
}
