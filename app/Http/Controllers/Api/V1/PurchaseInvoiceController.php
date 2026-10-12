<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PurchaseInvoiceIndexRequest;
use App\Http\Requests\Api\V1\ReceivePurchaseInvoiceRequest;
use App\Http\Resources\Api\V1\PurchaseInvoiceResource;
use App\Invoices\PurchaseLabelDocument;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceController extends Controller
{
    /**
     * The purchase invoices as the receiving page lists them: narrowed to one stage, and sorted by date, newest first
     * when the app does not ask for a direction. General invoices and deleted purchase invoices never appear.
     */
    public function index(PurchaseInvoiceIndexRequest $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->purchases()
            ->with('supplier')
            ->when($request->filled('stage'), fn (Builder $query): Builder => $query
                ->where('stage', $request->enum('stage', InvoiceStage::class)))
            ->orderBy('date', $request->sortDirection())
            ->orderBy('id', $request->sortDirection())
            ->paginate($request->integer('per_page') ?: 25)
            ->withQueryString();

        return PurchaseInvoiceResource::collection($invoices);
    }

    /**
     * One purchase invoice with its lines in their order, for counting what arrived. A general invoice is not found, like a deleted one.
     * A line keeps showing its item even after the item was deleted, because the goods it counts still arrive.
     */
    public function show(Invoice $invoice): PurchaseInvoiceResource
    {
        abort_unless($invoice->isPurchase(), 404);

        return new PurchaseInvoiceResource($invoice->load([
            'supplier',
            'items' => fn (HasMany $query): HasMany => $query->orderBy('id'),
            'items.item' => fn (BelongsTo $query): BelongsTo => $query->withTrashed(),
        ]));
    }

    /**
     * Receives the purchase invoice whose carton label was scanned: it moves straight to Received, whatever its stage.
     * Every carton of an invoice carries the same label, so scanning another one is not an error; the answer only says
     * the invoice was already received. A barcode that is not the label of a purchase invoice comes back as a 422
     * with its message in the request's language.
     *
     * @throws ValidationException
     */
    public function receive(ReceivePurchaseInvoiceRequest $request): PurchaseInvoiceResource
    {
        $invoice = PurchaseLabelDocument::invoiceOf($request->string('barcode')->value())
            ?? throw ValidationException::withMessages(['barcode' => __('api.receive.unknown_barcode')]);

        $alreadyReceived = $invoice->stage === InvoiceStage::Received;

        $invoice->receive();

        return (new PurchaseInvoiceResource($invoice->load('supplier')))
            ->additional(['already_received' => $alreadyReceived]);
    }
}
