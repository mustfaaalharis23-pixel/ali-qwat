<?php

namespace App\Models;

use App\Enums\OrderPaymentType;
use Database\Factories\CustomerPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['customer_id', 'invoice_id', 'user_id', 'amount', 'is_down_payment', 'date', 'image', 'notes'])]
class CustomerPayment extends BaseModel
{
    /** @use HasFactory<CustomerPaymentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A payment is not edited, only deleted. Deleting it deletes its allocations, so its orders get their remaining back,
     * and then its withdrawal invoice, so the postpaid box and the accountant's balance go back too.
     * The receipt image is removed from the public disk only when the payment is force deleted.
     */
    protected static function booted(): void
    {
        static::deleted(function (CustomerPayment $payment): void {
            $payment->allocations()->each(fn (CustomerPaymentOrder $allocation) => $allocation->delete());
            $payment->invoice?->delete();
        });

        static::forceDeleted(function (CustomerPayment $payment): void {
            if (filled($payment->image)) {
                Storage::disk('public')->delete($payment->image);
            }
        });
    }

    /**
     * The customer who paid.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The postpaid money withdrawal invoice created with this payment. It is found even when deleted, so a deleted payment still shows it.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * The user who recorded this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The shares of this payment on the customer's postpaid orders.
     *
     * @return HasMany<CustomerPaymentOrder, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentOrder::class);
    }

    /**
     * What the customer owed just before this payment, read the same way as the account statement: the customer's postpaid orders
     * up to this payment's date, orders of the same date included, minus the payments before it. On the same date, payments
     * follow the order they were recorded in. Deleted orders and deleted payments are not counted.
     */
    public function balanceBefore(): float
    {
        $date = $this->date->toDateString();

        $owed = (float) Order::query()
            ->where('customer_id', $this->customer_id)
            ->where('payment_type', OrderPaymentType::Postpaid)
            ->whereDate('date', '<=', $date)
            ->sum('total_amount');

        $paid = (float) static::query()
            ->where('customer_id', $this->customer_id)
            ->where(fn (Builder $query): Builder => $query
                ->whereDate('date', '<', $date)
                ->orWhere(fn (Builder $query): Builder => $query->whereDate('date', $date)->where($this->getKeyName(), '<', $this->getKey())))
            ->sum('amount');

        return $owed - $paid;
    }

    /**
     * What the customer still owed right after this payment.
     */
    public function balanceAfter(): float
    {
        return $this->balanceBefore() - (float) $this->amount;
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
            'is_down_payment' => 'boolean',
            'date' => 'date',
        ];
    }
}
