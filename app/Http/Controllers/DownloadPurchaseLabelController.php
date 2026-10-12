<?php

namespace App\Http\Controllers;

use App\Invoices\PurchaseLabelDocument;
use App\Models\Invoice;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The carton label of one purchase invoice as a PDF file, to send to the supplier's factory. It opens in the browser,
 * which can print or save it.
 * A general invoice has no label. It opens to a user who can view the invoice.
 */
class DownloadPurchaseLabelController extends Controller
{
    public function __invoke(Invoice $invoice, PurchaseLabelDocument $document): Response
    {
        abort_unless($invoice->isPurchase(), 404);

        Gate::authorize('view', $invoice);

        return response($document->pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                'label-'.PurchaseLabelDocument::number($invoice).'.pdf',
            ),
        ]);
    }
}
