<?php

namespace App\Http\Controllers;

use App\Invoices\AmountInWords;
use App\Invoices\InvoiceDocument;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Models\CustomerPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * The receipt (سند قبض) of one customer payment, for the small thermal printer: what was paid, in numbers and in words,
 * who paid it and who took it, and what the customer owed before it and still owes after it, the same as the account statement.
 * It opens to a user who can view the payment. A deleted payment has no receipt.
 */
class PrintPaymentReceiptController extends Controller
{
    public function __invoke(CustomerPayment $payment, InvoiceDocument $document): View
    {
        Gate::authorize('view', $payment);

        $payment->loadMissing(['customer', 'user']);

        return view('invoices.print.receipt', [
            'payment' => $payment,
            'previousBalance' => $payment->balanceBefore(),
            'remaining' => $payment->balanceAfter(),
            'words' => AmountInWords::spell((float) $payment->amount),
            'money' => fn (float $amount): string => FormattedValue::of($amount, Currency::IraqiDinar)->number,
            'currency' => Currency::IraqiDinar->symbol(),
            'fonts' => $document->fontFaces(),
        ]);
    }
}
