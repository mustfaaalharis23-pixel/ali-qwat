<?php

namespace App\Models;

use Database\Factories\PayoffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable(['invoice_id', 'amount', 'date', 'image'])]
class Payoff extends BaseModel
{
    /** @use HasFactory<PayoffFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Before every save, wherever it is saved from, the payoff must fit its invoice.
     * Saving, deleting, or restoring a payoff recalculates its invoice's paid amount and remaining.
     */
    protected static function booted(): void
    {
        static::saving(function (Payoff $payoff): void {
            $payoff->ensureFitsItsInvoice();
        });

        static::saved(function (Payoff $payoff): void {
            $payoff->recalculateInvoice();
        });

        static::deleted(function (Payoff $payoff): void {
            $payoff->recalculateInvoice();
        });
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
        ];
    }
}
