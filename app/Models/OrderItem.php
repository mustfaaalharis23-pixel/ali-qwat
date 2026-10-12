<?php

namespace App\Models;

use App\Enums\Package;
use App\Observers\StockObserver;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[ObservedBy(StockObserver::class)]
#[Fillable([
    'order_id',
    'item_id',
    'quantity',
    'package',
    'price',
])]
class OrderItem extends BaseModel
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A line is a piece unless it was sold in another package. The package is information only: the quantity is in pieces.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'package' => 'piece',
    ];

    /**
     * Before every save, the quantity and price must be more than zero, the item must have enough stock,
     * the total price is price × quantity, and the cost and the profit of the line are written with it.
     * The last item of an order that is not deleted cannot be deleted.
     * Saving or deleting an item recalculates its order's amounts.
     */
    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            $item->ensureQuantityAndPriceArePositive();
            $item->ensureTheItemHasEnoughStock();
            $item->total_price = $item->price * $item->quantity;
            $item->applyCostAndProfit();
        });

        static::deleting(function (OrderItem $item): void {
            $item->ensureIsNotTheLastItemOfItsOrder();
        });

        static::saved(function (OrderItem $item): void {
            Order::withTrashed()->find($item->order_id)?->recalculateAmounts();
        });

        static::deleted(function (OrderItem $item): void {
            Order::withTrashed()->find($item->order_id)?->recalculateAmounts();
        });
    }

    /**
     * The order of this item.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The item that is sold.
     *
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The line keeps what its item cost us the moment it was sold: the cost price is taken from the item when the line
     * is created, and when the line is moved to another item, and it never changes after that, not even when a later
     * purchase changes the item's cost. The line's cost and profit are worked out from it and from the quantity,
     * so the profit of an old sale stays what it was.
     */
    protected function applyCostAndProfit(): void
    {
        if (! $this->exists || $this->isDirty('item_id')) {
            $this->cost_price = (float) (Item::query()->find($this->item_id)?->cost_price ?? 0);
        }

        $costPrice = (float) $this->cost_price;

        $this->total_cost = $costPrice * (float) $this->quantity;
        $this->profit = ((float) $this->price - $costPrice) * (float) $this->quantity;
    }

    /**
     * An item is never sold for more than its available quantity, so the stock never goes below zero.
     * A line that is already saved counts its own quantity as available to it, because that quantity is already out of the stock.
     *
     * @throws ValidationException
     */
    protected function ensureTheItemHasEnoughStock(): void
    {
        $item = Item::query()->find($this->item_id);

        if ($item === null) {
            return;
        }

        $available = (float) $item->available_quantity;

        if ($this->exists && (int) $this->getOriginal('item_id') === (int) $this->item_id) {
            $available += (float) $this->getOriginal('quantity');
        }

        if ((float) $this->quantity > $available + 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => __('orders.validation.quantity_more_than_available', [
                    'item' => $item->name,
                    'available' => $available,
                ]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function ensureQuantityAndPriceArePositive(): void
    {
        if ($this->quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('orders.validation.quantity_must_be_positive'),
            ]);
        }

        if ($this->price <= 0) {
            throw ValidationException::withMessages([
                'price' => __('orders.validation.price_must_be_positive'),
            ]);
        }
    }

    /**
     * There is no order without items, so an order that is not deleted keeps at least one item.
     *
     * @throws ValidationException
     */
    protected function ensureIsNotTheLastItemOfItsOrder(): void
    {
        $order = Order::withTrashed()->find($this->order_id);

        if ($order === null || $order->trashed()) {
            return;
        }

        if (! $order->items()->whereKeyNot($this->getKey())->exists()) {
            throw ValidationException::withMessages([
                'order' => __('orders.validation.last_item_cannot_be_deleted'),
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
            'quantity' => 'float',
            'package' => Package::class,
            'price' => 'float',
            'total_price' => 'float',
            'cost_price' => 'float',
            'total_cost' => 'float',
            'profit' => 'float',
        ];
    }
}
