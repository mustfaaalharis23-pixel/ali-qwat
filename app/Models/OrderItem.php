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
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * One sold line. Its pieces are what leaves the stock, and its quantity is those pieces in dozens, because the price is per dozen:
 * 7 packets of 20 pieces are 140 pieces, 11.67 dozens, and the line costs 11.67 × the price of a dozen.
 * A line of an item with lens powers is sold at one, and a line of packets with colors carries the colors of one packet:
 * the seller chooses the packets' power and colors, and the packets leave with their colors.
 */
#[ObservedBy(StockObserver::class)]
#[Fillable([
    'order_id',
    'item_id',
    'lens_power_id',
    'colors',
    'quantity',
    'pieces',
    'package',
    'price',
    'discount',
])]
class OrderItem extends BaseModel
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * A line is sold by the packet. Its size is taken from the item when the line is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'package' => 'packet',
        'package_size' => 1,
        'discount' => 0,
    ];

    /**
     * Before every save, the line's pieces and dozens are written, they and the price must be more than zero, the pieces are whole
     * packets of the item, the item must have enough stock, the total price is price × dozens, and the pieces in the line's packet
     * and the cost and the profit of the line are written with it. The line's discount is its share of the order's discount,
     * never below zero, and it comes off the line's profit.
     * The last item of an order that is not deleted cannot be deleted.
     * Saving or deleting an item recalculates its order's amounts.
     */
    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            $item->applyPiecesAndDozens();
            $item->colors = Item::colorMix($item->colors) ?: null;
            $item->ensureQuantityAndPriceArePositive();
            $item->ensurePiecesAreWholePackets();
            $item->ensureTheItemHasEnoughStock();
            $item->total_price = $item->price * $item->quantity;
            $item->applyPackageSize();
            $item->applyCostAndProfit();
            $item->ensureDiscountFits();
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
     * The lens power the item was sold at, when it was bought with powers. Null for an item sold without one.
     *
     * @return BelongsTo<LensPower, $this>
     */
    public function lensPower(): BelongsTo
    {
        return $this->belongsTo(LensPower::class);
    }

    /**
     * The colors of one packet of the line, with their names: [{id, name, pieces}], from the lowest color.
     * An item sold without colors gives an empty list.
     *
     * @return list<array{id: int, name: ?string, pieces: float}>
     */
    public function colorsWithNames(): array
    {
        $mix = Item::colorMix($this->colors);
        $names = Color::withTrashed()->whereIn('id', array_column($mix, 'color_id'))->pluck('name', 'id');

        return array_map(fn (array $color): array => [
            'id' => $color['color_id'],
            'name' => $names[$color['color_id']] ?? null,
            'pieces' => $color['pieces'],
        ], $mix);
    }

    /**
     * The colors of one packet of the line as one short text, such as "Red 10 pcs, Blue 10 pcs", for the screens and the invoice.
     */
    public function colorsText(): string
    {
        return collect($this->colorsWithNames())
            ->map(fn (array $color): string => __('filament/resources/invoice_item.group.color', [
                'color' => $color['name'],
                'pieces' => Number::format($color['pieces'], maxPrecision: 2),
            ]))
            ->implode(__('filament/resources/invoice_item.group.separator'));
    }

    /**
     * The whole packets the line was sold in: its pieces over the pieces in its packet.
     */
    public function packets(): float
    {
        return (float) $this->pieces / max((int) $this->package_size, 1);
    }

    /**
     * The pieces are the line's truth, and its quantity is them in dozens. A line given pieces takes its dozens from them;
     * a line given only dozens (or whose dozens change) takes its pieces from them, and those must be whole pieces.
     *
     * @throws ValidationException
     */
    protected function applyPiecesAndDozens(): void
    {
        if ($this->pieces === null || ((int) $this->pieces === 0 && ! $this->isDirty('pieces')) || ($this->isDirty('quantity') && ! $this->isDirty('pieces'))) {
            $pieces = (float) $this->quantity * Item::PIECES_IN_A_DOZEN;

            if (abs($pieces - round($pieces)) >= 0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => __('orders.validation.quantity_must_be_whole_pieces'),
                ]);
            }

            $this->pieces = (int) round($pieces);
        }

        $this->quantity = (float) $this->pieces / Item::PIECES_IN_A_DOZEN;
    }

    /**
     * The line keeps how many pieces its packet held the moment it was sold: the item's pieces per packet.
     * It is taken when the line is created, and when its item changes, and it never changes after that,
     * not even when the item's packet changes, so an old invoice still reads as it was sold. An item without a packet size keeps 1.
     */
    protected function applyPackageSize(): void
    {
        if ($this->exists && ! $this->isDirty(['item_id', 'package'])) {
            return;
        }

        $this->package_size = $this->package->sizeFor(Item::query()->find($this->item_id)) ?? 1;
    }

    /**
     * The line keeps what a dozen of its item cost us the moment it was sold: the cost price is taken from the item when the line
     * is created, and when the line is moved to another item, and it never changes after that, not even when a later
     * purchase changes the item's cost. The line's cost and profit are worked out from it and from the dozens,
     * so the profit of an old sale stays what it was. The line's share of the order's discount comes off its profit.
     */
    protected function applyCostAndProfit(): void
    {
        if (! $this->exists || $this->isDirty('item_id')) {
            $this->cost_price = (float) (Item::query()->find($this->item_id)?->cost_price ?? 0);
        }

        $costPrice = (float) $this->cost_price;

        $this->total_cost = $costPrice * (float) $this->quantity;
        $this->profit = (float) $this->total_price - (float) $this->discount - (float) $this->total_cost;
    }

    /**
     * The line's discount is its share of the order's discount (see Order::applyDiscount), and it is never below zero.
     *
     * @throws ValidationException
     */
    protected function ensureDiscountFits(): void
    {
        if ((float) $this->discount < 0) {
            throw ValidationException::withMessages([
                'discount' => __('orders.validation.discount_must_not_be_negative'),
            ]);
        }
    }

    /**
     * A line of an item with a packet size is whole packets of it: 40 pieces of a packet of 20, never 30.
     *
     * @throws ValidationException
     */
    protected function ensurePiecesAreWholePackets(): void
    {
        $size = (int) (Item::query()->find($this->item_id)?->pieces_per_packet ?? 1);

        if ((int) $this->pieces % max($size, 1) === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => __('orders.validation.quantity_not_whole_packets', ['size' => $size]),
        ]);
    }

    /**
     * An item is never sold for more than its available quantity, so the stock never goes below zero,
     * never for more than is left of the lens power it is sold at, and never for more than is left of the packets of its power
     * and colors: a sale takes only from its own power and its own colors.
     * An item bought with powers is sold at one of them, so selling it without a power asks for the power,
     * and an item bought in mixes of colors at that power is sold in one of them, so selling it without colors asks for them.
     * A line that is already saved counts its own pieces as available to it, because they are already out of the stock.
     *
     * @throws ValidationException
     */
    protected function ensureTheItemHasEnoughStock(): void
    {
        $item = Item::query()->find($this->item_id);

        if ($item === null) {
            return;
        }

        $available = round((float) $item->available_quantity * Item::PIECES_IN_A_DOZEN, 4);

        if ($this->exists && (int) $this->getOriginal('item_id') === (int) $this->item_id) {
            $available += (float) $this->getOriginal('pieces');
        }

        if ((float) $this->pieces > $available + 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => __('orders.validation.quantity_more_than_available', [
                    'item' => $item->name,
                    'available' => round($available / Item::PIECES_IN_A_DOZEN, 2),
                ]),
            ]);
        }

        $this->ensureThePowerHasEnoughLeft($item);
        $this->ensureThePacketTypeHasEnoughLeft($item);
    }

    /**
     * The line's pieces are not more than is left of its lens power, or of the item's pieces without a power when it has none.
     *
     * @throws ValidationException
     */
    protected function ensureThePowerHasEnoughLeft(Item $item): void
    {
        $lensPowerId = $this->lens_power_id === null ? null : (int) $this->lens_power_id;
        $left = $item->availablePiecesOfPower($lensPowerId);

        if ($this->exists && ! $this->isDirty(['item_id', 'lens_power_id'])) {
            $left += (float) $this->getOriginal('pieces');
        }

        if ((float) $this->pieces <= $left + 0.0001) {
            return;
        }

        if ($lensPowerId === null && $item->wasBoughtWithPowers()) {
            throw ValidationException::withMessages([
                'lens_power_id' => __('orders.validation.choose_a_power', ['item' => $item->name]),
            ]);
        }

        throw ValidationException::withMessages([
            'quantity' => __('orders.validation.quantity_more_than_left_of_power', [
                'item' => $item->name,
                'power' => LensPower::query()->find($lensPowerId)?->value ?? __('filament/resources/lens_power.none'),
                'available' => round($left / Item::PIECES_IN_A_DOZEN, 2),
            ]),
        ]);
    }

    /**
     * The line's pieces are not more than is left of its packet type: the packets of its power with its colors.
     * A line without colors takes only from the packets that came in without colors at its power, so for an item bought
     * in mixes of colors at that power it asks for the colors.
     *
     * @throws ValidationException
     */
    protected function ensureThePacketTypeHasEnoughLeft(Item $item): void
    {
        $lensPowerId = $this->lens_power_id === null ? null : (int) $this->lens_power_id;

        if ($this->colors === null && ! $item->hasColorMixesAt($lensPowerId)) {
            return;
        }

        $left = $item->availablePiecesOfPacketType($lensPowerId, $this->colors);

        if ($this->exists && ! $this->isDirty(['item_id', 'lens_power_id', 'colors'])) {
            $left += (float) $this->getOriginal('pieces');
        }

        if ((float) $this->pieces <= $left + 0.0001) {
            return;
        }

        if ($this->colors === null) {
            throw ValidationException::withMessages([
                'colors' => __('orders.validation.choose_the_colors', ['item' => $item->name]),
            ]);
        }

        throw ValidationException::withMessages([
            'quantity' => __('orders.validation.quantity_more_than_left_of_packet_type', [
                'item' => $item->name,
                'available' => (int) floor(($left + 0.0001) / max((int) ($item->pieces_per_packet ?? 1), 1)),
            ]),
        ]);
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
            'pieces' => 'integer',
            'colors' => 'array',
            'package' => Package::class,
            'package_size' => 'integer',
            'price' => 'float',
            'total_price' => 'float',
            'discount' => 'float',
            'cost_price' => 'float',
            'total_cost' => 'float',
            'profit' => 'float',
        ];
    }
}
