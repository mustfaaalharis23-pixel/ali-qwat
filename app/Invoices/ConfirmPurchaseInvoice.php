<?php

namespace App\Invoices;

use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a purchase invoice for one side. The importer confirms first, then the receiver,
 * and each side writes the amount of the invoice to confirm it.
 */
class ConfirmPurchaseInvoice
{
    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $checker, Invoice $invoice, float $amount, InvoiceConfirmation $side): void
    {
        Gate::forUser($checker)->authorize($side->ability(), $invoice);

        $this->ensureTheInvoiceIsWaitingForThisSide($invoice, $side);
        $this->ensureTheEnteredAmountIsTheInvoiceAmount($invoice, $amount);

        $invoice->forceFill([
            $side->checkerColumn() => $checker->id,
            $side->confirmedAtColumn() => now(),
            'status' => $side->nextStatus(),
        ])->save();
    }

    /**
     * The importer's side is confirmed the moment a purchase invoice is created: whoever creates it is its importer,
     * so it is born waiting for the receiver only. No permission is asked for and no amount is written again,
     * because creating the invoice is the confirmation itself.
     * An invoice that is not a purchase, or one already past this stage, is left exactly as it is.
     */
    public function handleOnCreation(Invoice $invoice): void
    {
        if (! $invoice->isPurchase() || $invoice->status !== InvoiceStatus::Pending) {
            return;
        }

        $side = InvoiceConfirmation::Importer;

        $invoice->forceFill([
            $side->checkerColumn() => $invoice->user_id,
            $side->confirmedAtColumn() => now(),
            'status' => $side->nextStatus(),
        ])->save();
    }

    /**
     * Only a purchase invoice that is not deleted, has its images, and is at this side's stage can be confirmed.
     * The receiver's stage comes only after the importer confirmed, so this is what keeps the two confirmations in order.
     *
     * @throws ValidationException
     */
    protected function ensureTheInvoiceIsWaitingForThisSide(Invoice $invoice, InvoiceConfirmation $side): void
    {
        if (! $invoice->isPurchase()) {
            throw ValidationException::withMessages([
                'status' => __('invoices.validation.only_purchase_invoice_is_confirmed'),
            ]);
        }

        if ($invoice->trashed()) {
            throw ValidationException::withMessages([
                'status' => __('invoices.validation.deleted_invoice_is_not_confirmed'),
            ]);
        }

        if ($side === InvoiceConfirmation::Importer && blank($invoice->images)) {
            throw ValidationException::withMessages([
                'images' => __('invoices.validation.invoice_without_images_is_not_confirmed'),
            ]);
        }

        if ($invoice->status !== $side->requiredStatus()) {
            throw ValidationException::withMessages([
                'status' => __('invoices.validation.invoice_is_not_waiting_for_this_confirmation'),
            ]);
        }
    }

    /**
     * The side that confirms must write the invoice amount, so it is confirmed on purpose.
     *
     * @throws ValidationException
     */
    protected function ensureTheEnteredAmountIsTheInvoiceAmount(Invoice $invoice, float $amount): void
    {
        if (abs($amount - (float) $invoice->amount) > 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('invoices.validation.entered_amount_is_not_the_invoice_amount'),
            ]);
        }
    }
}
