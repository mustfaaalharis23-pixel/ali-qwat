<?php

namespace App\Models;

use App\Enums\OrderPaymentType;
use App\Metrics\Period;
use App\Observers\StockObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[ObservedBy(StockObserver::class)]
#[Fillable([
    'customer_id',
    'user_id',
    'cashier_id',
    'payment_type',
    'date',
    'notes',
    'down_payment',
    'weekly_payment',
])]
class Order extends BaseModel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * An order that has payments on it cannot be deleted; its payments are deleted first.
     * Deleting an order soft deletes all of its items. It runs after the order is deleted,
     * so the last item is allowed to go with its order.
     */
    protected static function booted(): void
    {
        static::deleting(function (Order $order): void {
            $order->ensureHasNoPayments();
        });

        static::deleted(function (Order $order): void {
            $order->items()->each(fn (OrderItem $item) => $item->delete());
        });
    }

    /**
     * The sales of the payment type inside the period: the sum of its orders' total amounts, only of the given cashier when one is given.
     * The total amount is what the order finally costs, so a discount on the order is counted. Deleted orders are not counted.
     * This is the only definition of the sales of a payment type.
     */
    public static function sales(OrderPaymentType $paymentType, Period $period, ?User $cashier = null): float
    {
        $query = static::query()
            ->where('payment_type', $paymentType)
            ->when($cashier, fn (Builder $query): Builder => $query->where('cashier_id', $cashier->id));

        return (float) $period->applyTo($query, 'date')->sum('total_amount');
    }

    /**
     * The balance of the box of the payment type inside the period: its sales minus the money withdrawn from it,
     * only of the given cashier when one is given. This is the only definition of the balance of a box.
     */
    public static function boxBalance(OrderPaymentType $paymentType, Period $period, ?User $cashier = null): float
    {
        return static::sales($paymentType, $period, $cashier) - InvoiceItem::withdrawn($paymentType, $period, $cashier);
    }

    /**
     * The sales of the period, whatever their payment type: the sum of the total amounts of its orders.
     * The total amount is what the order finally costs, so a discount on the order is counted. Deleted orders are not counted.
     * This is the only definition of the sales of a period.
     */
    public static function salesAmount(Period $period): float
    {
        return (float) $period->applyTo(static::query(), 'date')->sum('total_amount');
    }

    /**
     * The profit of the sales inside the period: the sum of the profits its orders kept.
     * Every sale line froze what its item cost the moment it was sold, so the profit of an old sale never moves
     * when a later purchase changes the cost. Deleted orders are not counted.
     * This is the only definition of the profit of the sales.
     */
    public static function salesProfit(Period $period): float
    {
        return (float) $period->applyTo(static::query(), 'date')->sum('profit');
    }

    /**
     * The debts of the customers: postpaid orders that still have a remaining. This is the only definition of a customer debt.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function debts(Builder $query): void
    {
        $query
            ->where('payment_type', OrderPaymentType::Postpaid)
            ->where('remaining', '>', 0);
    }

    /**
     * Sets the amount to the sum of the items' total prices (before their discounts) and the total amount to that minus the items'
     * discounts, then the paid amount and remaining of the total amount, and the order's cost and profit from the costs and profits
     * its items kept, and saves the order.
     * A postpaid order is paid by the allocations of its customer's payments; a cash order is fully paid when it is sold.
     * A deleted order keeps its amounts.
     */
    public function recalculateAmounts(): void
    {
        if ($this->trashed()) {
            return;
        }

        $amount = (float) $this->items()->sum('total_price');
        $totalAmount = $amount - (float) $this->items()->sum('discount');
        $paidAmount = $this->payment_type === OrderPaymentType::Postpaid
            ? (float) $this->allocations()->sum('amount')
            : $totalAmount;

        $this->amount = $amount;
        $this->total_amount = $totalAmount;
        $this->paid_amount = $paidAmount;
        $this->remaining = $totalAmount - $paidAmount;
        $this->total_cost = (float) $this->items()->sum('total_cost');
        $this->profit = (float) $this->items()->sum('profit');
        $this->save();
    }

    /**
     * Takes the discount off the whole order: it is spread over the order's lines by each line's share of the order's amount, in whole
     * dinars, and the last line takes what is left, so the shares always add up to the discount. Each line keeps its share, so the
     * profit of every item stays true. The discount is never below zero, it is always less than the order's amount, and what the
     * order comes to after it is never below what the order cost us. Then the order's amounts are worked out again.
     *
     * @throws ValidationException
     */
    public function applyDiscount(float $discount): void
    {
        $lines = $this->items()->orderBy('id')->get();
        $amount = (float) $lines->sum('total_price');

        $this->ensureDiscountFits($discount, $amount, (float) $lines->sum('total_cost'));

        $left = $discount;

        foreach ($lines->values() as $index => $line) {
            $share = $index === $lines->count() - 1 ? $left : round($discount * (float) $line->total_price / $amount);
            $left -= $share;

            if (abs((float) $line->discount - $share) > 0.0001) {
                $line->discount = $share;
                $line->save();
            }
        }

        $this->recalculateAmounts();
    }

    /**
     * A discount is never below zero, it is always less than the order's amount, and it never takes the order below its cost.
     *
     * @throws ValidationException
     */
    protected function ensureDiscountFits(float $discount, float $amount, float $cost): void
    {
        if ($discount < 0) {
            throw ValidationException::withMessages([
                'discount' => __('orders.validation.discount_must_not_be_negative'),
            ]);
        }

        if ($discount === 0.0) {
            return;
        }

        if ($discount >= $amount - 0.0001) {
            throw ValidationException::withMessages([
                'discount' => __('orders.validation.discount_more_than_order'),
            ]);
        }

        if ($amount - $discount < $cost - 0.0001) {
            throw ValidationException::withMessages([
                'discount' => __('orders.validation.discount_below_order_cost'),
            ]);
        }
    }

    /**
     * The items of this order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The shares of the customer's payments that paid this order.
     *
     * @return HasMany<CustomerPaymentOrder, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentOrder::class);
    }

    /**
     * The customer of this order.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who created this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The cashier who sold this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @throws ValidationException
     */
    protected function ensureHasNoPayments(): void
    {
        if ($this->allocations()->exists()) {
            throw ValidationException::withMessages([
                'order' => __('orders.validation.order_has_payments'),
            ]);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_type' => OrderPaymentType::class,
            'date' => 'date',
            'amount' => 'float',
            'total_amount' => 'float',
            'paid_amount' => 'float',
            'remaining' => 'float',
            'down_payment' => 'float',
            'weekly_payment' => 'float',
            'total_cost' => 'float',
            'profit' => 'float',
        ];
    }
}
