<?php

namespace App\Http\Controllers;

use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * The checking sheet of one purchase invoice: the paper the employee who receives the goods from the factory holds
 * in their hand while counting them. Every line shows its item's picture and name, the price of one piece in yuan, the landed
 * price of a piece and of a dozen in dinars (with the transport), its colors, and its powers, each with its packets.
 * It opens to a user who can open the invoice, and only for a purchase invoice.
 */
class PrintPurchaseInvoiceController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        abort_unless($invoice->isPurchase(), 404);

        $invoice->loadMissing(['supplier', 'category', 'container', 'items.item', 'items.specificDescription', 'items.groups.lensPower', 'items.colors.color']);

        return view('invoices.print.purchase', [
            'invoice' => $invoice,
            'number' => fn (?float $value): string => $value === null ? '—' : FormattedValue::of($value)->number,
            'money' => fn (float $amount): string => FormattedValue::of($amount, Currency::ChineseYuan)->number,
            'currency' => Currency::ChineseYuan->symbol(),
            'dinars' => fn (float $amount): string => FormattedValue::of($amount, Currency::IraqiDinar)->number,
            'dinar' => Currency::IraqiDinar->symbol(),
        ]);
    }
}
