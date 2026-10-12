<?php

namespace App\Models;

use Database\Factories\PayoffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable(['invoice_id', 'amount', 'date', 'image', 'is_initial_payment'])]
class Payoff extends BaseModel
{
    /** @use HasFactory<PayoffFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Set only while the invoice of this initial payment deletes it, the one way an initial payment is ever deleted.
     */
    protected bool $goesWithItsInvoice = false;

    /**
     * Set only while part of this payoff moves between an invoice and a part split off it, the one way an initial payment is ever changed.
     */
    protected bool $movesToASplitInvoice = false;

    /**
     * Before every save, wherever it is saved from, the payoff must fit its invoice, and an initial payment is never changed.
     * An initial payment is never deleted on its own, only with its invoice.
     * Saving, deleting, or restoring a payoff recalculates its invoice's paid amount and remaining.
     */
    protected static function booted(): void
    {
        static::saving(function (Payoff $payoff): void {
            $payoff->ensureInitialPaymentIsNotChanged();
            $payoff->ensureFitsItsInvoice();
        });

        static::deleting(function (Payoff $payoff): void {
            $payoff->ensureInitialPaymentGoesOnlyWithItsInvoice();
        });

        static::saved(function (Payoff $payoff): void {
            $payoff->recalculateInvoice();
        });

        static::deleted(function (Payoff $payoff): void {
            $payoff->recalculateInvoice();
        });
    }

    /**
     * Deletes this initial payment together with its invoice. The invoice calls it after it is deleted.
     */
    public function deleteWithItsInvoice(): void
    {
        $this->goesWithItsInvoice = true;

        try {
            $this->delete();
        } finally {
            $this->goesWithItsInvoice = false;
        }
    }

    /**
     * Moves the given amount of this payoff between an invoice and a part split off it: to the part when the invoice is split,
     * or back to the original when the part is merged back. The same payment, with its date, image, and initial payment flag,
     * is created on the other invoice for that amount, and this payoff keeps the rest.
     * When the whole payoff moves, it is removed for good, so restoring its invoice never brings it back.
     * An initial payment is moved this way too, because it is still what was paid when the invoice was created.
     */
    public function moveToSplitInvoice(Invoice $invoice, float $amount): Payoff
    {
        $moved = static::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'date' => $this->date,
            'image' => $this->image,
            'is_initial_payment' => $this->is_initial_payment,
        ]);

        $this->movesToASplitInvoice = true;

        try {
            if ((float) $this->amount - $amount < 0.0001) {
                $this->forceDelete();
            } else {
                $this->amount = (float) $this->amount - $amount;
                $this->save();
            }
        } finally {
            $this->movesToASplitInvoice = false;
        }

        return $moved;
    }

    /**
     * The invoice this payoff pays.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * A payoff stays on the invoice it was created for, the invoice must not be deleted, it must be a purchase invoice,
     * the payoff amount must be more than zero, and the invoice's payoffs cannot add up to more than its amount.
     *
     * @throws ValidationException
     */
    protected function ensureFitsItsInvoice(): void
    {
        if ($this->exists && $this->isDirty('invoice_id')) {
            throw ValidationException::withMessages([
                'invoice_id' => __('invoices.validation.payoff_invoice_cannot_change'),
            ]);
        }

        $invoice = Invoice::withTrashed()->findOrFail($this->invoice_id);

        if ($invoice->trashed()) {
            throw ValidationException::withMessages([
                'invoice_id' => __('invoices.validation.payoff_on_deleted_invoice'),
            ]);
        }

        if (! $invoice->isPurchase()) {
            throw ValidationException::withMessages([
                'invoice_id' => __('invoices.validation.payoff_needs_purchase_invoice'),
            ]);
        }

        if ((float) $this->amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('invoices.validation.payoff_amount_must_be_positive'),
            ]);
        }

        $otherPayoffs = (float) Payoff::query()
            ->where('invoice_id', $invoice->id)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->sum('amount');

        if ($otherPayoffs + (float) $this->amount > (float) $invoice->amount + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('invoices.validation.payoffs_exceed_amount'),
            ]);
        }
    }

    /**
     * The initial payment is what was paid when the invoice was created, so once saved it is never changed:
     * not its amount, its date, its image, or its invoice, and a payoff never becomes or stops being an initial payment.
     * Restoring it with its invoice only brings it back, so that is allowed.
     *
     * @throws ValidationException
     */
    protected function ensureInitialPaymentIsNotChanged(): void
    {
        if (! $this->exists || $this->movesToASplitInvoice) {
            return;
        }

        $changesTheInitialPayment = $this->getOriginal('is_initial_payment')
            && $this->isDirty(['invoice_id', 'amount', 'date', 'image']);

        if ($changesTheInitialPayment || $this->isDirty('is_initial_payment')) {
            throw ValidationException::withMessages([
                'amount' => __('invoices.validation.initial_payment_cannot_change'),
            ]);
        }
    }

    /**
     * An initial payment is never deleted or force deleted on its own; it goes only with its invoice.
     *
     * @throws ValidationException
     */
    protected function ensureInitialPaymentGoesOnlyWithItsInvoice(): void
    {
        if ($this->is_initial_payment && ! $this->goesWithItsInvoice && ! $this->movesToASplitInvoice) {
            throw ValidationException::withMessages([
                'payoff' => __('invoices.validation.initial_payment_cannot_be_deleted'),
            ]);
        }
    }

    /**
     * Recalculates the paid amount and remaining of this payoff's invoice.
     */
    protected function recalculateInvoice(): void
    {
        Invoice::withTrashed()->find($this->invoice_id)?->recalculateAmounts();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'date' => 'date',
            'is_initial_payment' => 'boolean',
        ];
    }
}
