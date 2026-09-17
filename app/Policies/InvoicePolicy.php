<?php

namespace App\Policies;

use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InvoicePolicy extends ModelPolicy
{
    /**
     * A purchase invoice is updated while the receiver has not confirmed it, within 24 hours of creating it.
     * A general invoice is updated by a super user only, who passes through Gate::before.
     *
     * @param  Invoice  $model
     */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model)
            && $model->isPurchase()
            && $model->status !== InvoiceStatus::Confirmed
            && $this->isWithinADayOfCreating($model);
    }

    /**
     * A general invoice is deleted within 24 hours of creating it.
     * A purchase invoice is deleted by a super user only, who passes through Gate::before.
     *
     * @param  Invoice  $model
     */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model)
            && ! $model->isPurchase()
            && $this->isWithinADayOfCreating($model);
    }

    /**
     * The importer confirms a pending purchase invoice.
     */
    public function confirmAsImporter(User $user, Invoice $invoice): bool
    {
        return $this->canConfirm($user, $invoice, InvoiceConfirmation::Importer);
    }

    /**
     * The receiver confirms a purchase invoice the importer already confirmed.
     */
    public function confirmAsReceiver(User $user, Invoice $invoice): bool
    {
        return $this->canConfirm($user, $invoice, InvoiceConfirmation::Receiver);
    }

    /**
     * A side confirms a purchase invoice that is at its stage, with that side's permission.
     */
    protected function canConfirm(User $user, Invoice $invoice, InvoiceConfirmation $side): bool
    {
        return $invoice->isPurchase()
            && ! $invoice->trashed()
            && $invoice->status === $side->requiredStatus()
            && $this->hasPermission($user, $side->ability());
    }

    /**
     * Whether the invoice was created less than 24 hours ago.
     */
    protected function isWithinADayOfCreating(Invoice $invoice): bool
    {
        return $invoice->created_at?->gt(now()->subDay()) === true;
    }
}
