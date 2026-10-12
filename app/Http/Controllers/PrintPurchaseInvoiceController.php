<?php

namespace App\Http\Controllers;

use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * The checking sheet of one purchase invoice: the paper the employee who receives the goods from the factory holds
 * in their hand while counting them. Every line shows its item's picture and name, its packets, packet capacity,
 * cartons, the price of one piece, and the line's total, with an empty box to tick and room to write what was found.
 * It opens to a user who can open the invoice, and only for a purchase invoice.
 */
class PrintPurchaseInvoiceController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        abort_unless($invoice->isPurchase(), 404);

        $invoice->loadMissing(['supplier', 'category', 'container', 'items.item', 'items.specificDescription']);

        return view('invoices.print.purchase', [
            'invoice' => $invoice,
            'number' => fn (?float $value): string => $value === null ? '—' : FormattedValue::of($value)->number,
            'money' => fn (float $amount): string => FormattedValue::of($amount, Currency::ChineseYuan)->number,
            'currency' => Currency::ChineseYuan->symbol(),
        ]);
    }
}
