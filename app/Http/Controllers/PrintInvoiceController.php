<?php

namespace App\Http\Controllers;

use App\Filament\Pages\InvoicePrinting;
use App\Invoices\InvoiceDocument;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * The printable invoice of one order, in the size it is printed on: a4 is the full page invoice,
 * and pos is the receipt of the small printer. Deleted orders have no invoice.
 * It opens to a user who can open the invoice printing page. It is the customer's invoice, so what the items cost us,
 * the profit, and the margin are never on it, not on the paper and not on the screen.
 */
class PrintInvoiceController extends Controller
{
    public function __invoke(Order $order, string $format, InvoiceDocument $document): View
    {
        Gate::authorize('viewAny', InvoicePrinting::class);

        return $document->view($order, $format);
    }
}
