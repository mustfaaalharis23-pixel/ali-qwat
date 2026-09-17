<?php

namespace App\Invoices;

use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Takes back the last confirmation of a purchase invoice, for a super user only:
 * a confirmed invoice goes back to the importer's stage, and an invoice the importer confirmed goes back to pending.
 */
class RevertInvoiceConfirmation
{
    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Invoice $invoice): void
    {
        if (! $user->is_super) {
            throw new AuthorizationException(__('invoices.validation.only_a_super_user_reverts_a_confirmation'));
        }

        $side = $this->lastConfirmation($invoice);

        $invoice->forceFill([
            $side->checkerColumn() => null,
            $side->confirmedAtColumn() => null,
            'status' => $side->requiredStatus(),
        ])->save();
    }

    /**
     * The side that confirmed last, which is the one taken back.
     *
     * @throws ValidationException
     */
    protected function lastConfirmation(Invoice $invoice): InvoiceConfirmation
    {
        return match ($invoice->status) {
            InvoiceStatus::Confirmed => InvoiceConfirmation::Receiver,
            InvoiceStatus::ImporterConfirmed => InvoiceConfirmation::Importer,
            default => throw ValidationException::withMessages([
                'status' => __('invoices.validation.invoice_has_no_confirmation_to_revert'),
            ]),
        };
    }
}
