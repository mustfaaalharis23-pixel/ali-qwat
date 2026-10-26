<?php

namespace App\Models;

use App\Enums\CustomerPaymentType;
use Carbon\CarbonInterface;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'phone', 'email', 'contract', 'payment_type', 'is_invoice_by_invoice'])]
class Customer extends BaseModel
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The contract file is removed from the public disk when it is replaced or removed, or when the customer is force deleted.
     * A soft deleted customer keeps its file, so it can be restored.
     */
    protected static function booted(): void
    {
        static::updated(function (Customer $customer): void {
            if ($customer->wasChanged('contract') && filled($customer->getRawOriginal('contract'))) {
                Storage::disk('public')->delete($customer->getRawOriginal('contract'));
            }
        });

        static::forceDeleted(function (Customer $customer): void {
            if (filled($customer->contract)) {
                Storage::disk('public')->delete($customer->contract);
            }
        });
    }

    /**
     * The orders of this customer.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The order items of this customer, through its orders.
     *
     * @return HasManyThrough<OrderItem, Order, $this>
     */
    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Order::class);
    }

    /**
     * The total sales to this customer: the sum of the total amounts of its orders that are not deleted.
     */
    public function totalSales(): float
    {
        return (float) $this->orders()->sum('total_amount');
    }

    /**
     * The number of this customer's orders that are not deleted.
     */
    public function ordersCount(): int
    {
        return $this->orders()->count();
    }

    /**
     * The average total amount of this customer's orders, or zero when it has none.
     */
    public function averageOrderValue(): float
    {
        return (float) $this->orders()->avg('total_amount');
    }

    /**
     * The number of different items this customer bought in its orders that are not deleted.
     */
    public function itemsCount(): int
    {
        return $this->orderItems()->distinct()->count('order_items.item_id');
    }

    /**
     * The date of this customer's latest order that is not deleted, or null when it has none.
     */
    public function lastOrderDate(): ?CarbonInterface
    {
        return $this->orders()->latest('date')->value('date');
    }

    /**
     * The whole days from this customer's last order until today, or null when it has no orders.
     */
    public function daysSinceLastOrder(): ?int
    {
        return $this->daysSince($this->lastOrderDate());
    }

    /**
     * The payments of this customer.
     *
     * @return HasMany<CustomerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * What this customer still owes: the sum of the remaining of its debts, from Order::debts().
     */
    public function debt(): float
    {
        return (float) $this->orders()->debts()->sum('remaining');
    }

    /**
     * The total this customer has paid: the sum of the amounts of its payments that are not deleted.
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * The date of this customer's latest payment that is not deleted, or null when it has none.
     */
    public function lastPaymentDate(): ?CarbonInterface
    {
        return $this->payments()->latest('date')->value('date');
    }

    /**
     * The whole days from this customer's last payment until today, or null when it has no payments.
     */
    public function daysSinceLastPayment(): ?int
    {
        return $this->daysSince($this->lastPaymentDate());
    }

    /**
     * The whole days from the date until today, or null when there is no date.
     */
    private function daysSince(?CarbonInterface $date): ?int
    {
        return $date === null ? null : (int) $date->diffInDays(today());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_type' => CustomerPaymentType::class,
            'is_invoice_by_invoice' => 'boolean',
        ];
    }
}
