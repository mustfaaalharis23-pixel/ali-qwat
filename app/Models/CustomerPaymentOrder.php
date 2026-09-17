<?php

namespace App\Models;

use App\Enums\OrderPaymentType;
use Database\Factories\CustomerPaymentOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable(['customer_payment_id', 'order_id', 'amount'])]
class CustomerPaymentOrder extends BaseModel
{
    /** @use HasFactory<CustomerPaymentOrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Before every save, wherever it is saved from, the allocation must fit its order.
     * Saving or deleting an allocation recalculates its order's paid amount and remaining.
     */
    protected static function booted(): void
    {
        static::saving(function (CustomerPaymentOrder $allocation): void {
            $allocation->ensureFitsItsOrder();
        });

        static::saved(function (CustomerPaymentOrder $allocation): void {
            $allocation->recalculateOrder();
        });

        static::deleted(function (CustomerPaymentOrder $allocation): void {
            $allocation->recalculateOrder();
        });
    }

    /**
     * The payment this allocation is a share of.
     *
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    /**
     * The order this allocation pays.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * An allocation stays on the order it was created for, the order must not be deleted, it must be a postpaid order,
     * the amount must be more than zero, and the order's allocations cannot add up to more than its total amount.
     *
     * @throws ValidationException
     */
    protected function ensureFitsItsOrder(): void
    {
        if ($this->exists && $this->isDirty('order_id')) {
            throw ValidationException::withMessages([
                'order_id' => __('orders.validation.payment_order_cannot_change'),
            ]);
        }

        $order = Order::withTrashed()->findOrFail($this->order_id);

        if ($order->trashed()) {
            throw ValidationException::withMessages([
                'order_id' => __('orders.validation.payment_on_deleted_order'),
            ]);
        }

        if ($order->payment_type !== OrderPaymentType::Postpaid) {
            throw ValidationException::withMessages([
                'order_id' => __('orders.validation.payment_needs_postpaid_order'),
            ]);
        }

        if ((float) $this->amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('orders.validation.payment_amount_must_be_positive'),
            ]);
        }

        $otherAllocations = (float) static::query()
            ->where('order_id', $order->id)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->sum('amount');

        if ($otherAllocations + (float) $this->amount > (float) $order->total_amount + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => __('orders.validation.payments_exceed_order_amount'),
            ]);
        }
    }

    /**
     * Recalculates the paid amount and remaining of this allocation's order.
     */
    protected function recalculateOrder(): void
    {
        Order::withTrashed()->find($this->order_id)?->recalculateAmounts();
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
        ];
    }
}
