<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'image', 'description', 'is_active', 'supplier_id', 'sale_price', 'pieces_per_packet'])]
class Item extends BaseModel
{
    /**
     * The pieces in one dozen. The stock and the purchases are counted in pieces, and the item and its sales speak in dozens:
     * its stock is shown in dozens, and its sale price and its cost are the price of one dozen.
     */
    public const PIECES_IN_A_DOZEN = 12;

    /**
     * The pieces a packet can hold. An item is packed in one of them, and its packet never changes.
     *
     * @var list<int>
     */
    public const PACKET_SIZES = [20, 24];

    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The image file is removed from the public disk when it is replaced or when the item is force deleted.
     * A soft deleted item keeps its file, so it can be restored.
     *
     * Every saved item has a specific description under the purchases description with the item's name,
     * and force deleting the item force deletes that description first.
     */
    protected static function booted(): void
    {
        static::saved(function (Item $item): void {
            $item->syncSpecificDescription();
        });

        static::updated(function (Item $item): void {
            if ($item->wasChanged('image')) {
                Storage::disk('public')->delete($item->getRawOriginal('image'));
            }
        });

        static::forceDeleting(function (Item $item): void {
            $item->specificDescription()->withTrashed()->first()?->forceDeleteQuietly();
        });

        static::forceDeleted(function (Item $item): void {
            Storage::disk('public')->delete($item->image);
        });
    }

    /**
     * The specific description of this item, under the purchases description.
     *
     * @return HasOne<SpecificDescription, $this>
     */
    public function specificDescription(): HasOne
    {
        return $this->hasOne(SpecificDescription::class);
    }

    /**
     * The stock batches of this item, one for every purchase line it came in on.
     *
     * @return HasMany<Batch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * The sale lines of this item, one for every time it was sold on an order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The supplier (company) of this item. An item can have no supplier.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The active items. An inactive item is hidden across the system, so every list or count of items for work uses this.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * What the stock is worth now: for every active item that still has a quantity, its available dozens times
     * what one dozen of it last cost, all added up. An inactive item is out of the system everywhere, so it is not counted,
     * and an item without a quantity adds nothing. This is the only definition of the value of the stock.
     */
    public static function stockValue(): float
    {
        return (float) static::query()
            ->active()
            ->where('available_quantity', '>', 0)
            ->sum(DB::raw('available_quantity * cost_price'));
    }

    /**
     * What the stock was worth at the end of that day, worked out again from the beginning: for every active item,
     * the dozens it still had then times what one dozen of it cost then, all added up.
     * What it had then is the pieces that came in on the purchase invoices both sides confirmed and dated up to that day,
     * minus the pieces sold on the orders dated up to it. What a dozen cost then is 12 × the piece price of the last such purchase.
     * Nothing is read from the item's own columns, because those hold today's numbers only.
     * An item that had no quantity then adds nothing, exactly like the value of the stock now.
     * This is the only definition of the value of the stock at a moment.
     */
    public static function stockValueAt(CarbonImmutable $moment): float
    {
        $purchases = Batch::query()
            ->whereHas('invoice', fn (Builder $query): Builder => $query
                ->where('status', InvoiceStatus::Confirmed)
                ->where('date', '<=', $moment))
            ->orderBy('invoice_id')
            ->orderBy('id')
            ->get(['item_id', 'quantity', 'purchase_price']);

        $boughtQuantities = [];
        $costPrices = [];

        foreach ($purchases as $batch) {
            $boughtQuantities[$batch->item_id] = ($boughtQuantities[$batch->item_id] ?? 0.0) + (float) $batch->quantity;

            if ((float) $batch->quantity > 0 && (float) $batch->purchase_price > 0) {
                $costPrices[$batch->item_id] = round((float) $batch->purchase_price * self::PIECES_IN_A_DOZEN, 2);
            }
        }

        $soldQuantities = OrderItem::query()
            ->whereHas('order', fn (Builder $query): Builder => $query->where('date', '<=', $moment))
            ->toBase()
            ->selectRaw('item_id, sum(pieces) as pieces')
            ->groupBy('item_id')
            ->pluck('pieces', 'item_id');

        return (float) static::query()
            ->active()
            ->pluck('id')
            ->sum(function (int $itemId) use ($boughtQuantities, $soldQuantities, $costPrices): float {
                $pieces = ($boughtQuantities[$itemId] ?? 0.0) - (float) ($soldQuantities[$itemId] ?? 0.0);

                return $pieces > 0 ? $pieces / self::PIECES_IN_A_DOZEN * ($costPrices[$itemId] ?? 0.0) : 0.0;
            });
    }

    /**
     * The available quantity, in dozens: the pieces in stock over the pieces in a dozen. The stock is counted in pieces,
     * so it is always exact, and it is shown in dozens. This is the only definition of what the factory has.
     */
    public function availableQuantity(): float
    {
        return $this->availablePieces() / self::PIECES_IN_A_DOZEN;
    }

    /**
     * The pieces in stock: what came in on the purchase invoices both sides confirmed, minus every piece sold on the orders that are
     * not deleted. Nothing else enters it — a quantity that only the importer confirmed is not in the stock and is never counted here.
     */
    public function availablePieces(): float
    {
        $bought = (float) $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)->sum('quantity');
        $sold = (float) OrderItem::query()
            ->where('item_id', $this->id)
            ->whereHas('order')
            ->sum('pieces');

        return $bought - $sold;
    }

    /**
     * The incoming quantity, in dozens: what was bought and did not arrive yet, meaning the importer confirmed its invoice
     * and the receiver did not. It is information for the data entry only: it has no relation whatsoever to the
     * available quantity or to selling, and it becomes available only when both sides confirm the invoice.
     */
    public function incomingQuantity(): float
    {
        return (float) $this->batchesOfInvoicesWith(InvoiceStatus::ImporterConfirmed)->sum('quantity') / self::PIECES_IN_A_DOZEN;
    }

    /**
     * The pieces left of this item at one lens power, or without a power when it is null: what came in at that power on the purchase
     * invoices both sides confirmed, minus what was sold at it on the orders that are not deleted. A sale takes only from its own power.
     */
    public function availablePiecesOfPower(?int $lensPowerId): float
    {
        $bought = (float) $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)
            ->where('lens_power_id', $lensPowerId)
            ->sum('quantity');

        $sold = (float) OrderItem::query()
            ->where('item_id', $this->id)
            ->where('lens_power_id', $lensPowerId)
            ->whereHas('order')
            ->sum('pieces');

        return $bought - $sold;
    }

    /**
     * Whether this item came in at any lens power on a purchase invoice both sides confirmed. Such an item is sold at a power.
     */
    public function wasBoughtWithPowers(): bool
    {
        return $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)->whereNotNull('lens_power_id')->exists();
    }

    /**
     * The lens powers this item can be sold at, with what is left of each in dozens, from the lowest: the powers that still have pieces,
     * and the pieces without a power last when some are left too. An item never bought with a power gives an empty list,
     * because it is sold without one, as before.
     *
     * @return list<array{id: ?int, value: ?string, available_quantity: float}>
     */
    public function powersLeft(): array
    {
        if (! $this->wasBoughtWithPowers()) {
            return [];
        }

        $bought = $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)
            ->toBase()
            ->selectRaw('lens_power_id, sum(quantity) as pieces')
            ->groupBy('lens_power_id')
            ->pluck('pieces', 'lens_power_id');

        $sold = OrderItem::query()
            ->where('item_id', $this->id)
            ->whereHas('order')
            ->toBase()
            ->selectRaw('lens_power_id, sum(pieces) as pieces')
            ->groupBy('lens_power_id')
            ->pluck('pieces', 'lens_power_id');

        $values = LensPower::withTrashed()->whereIn('id', $bought->keys()->filter())->pluck('value', 'id');

        return $bought
            ->map(fn (mixed $pieces, int|string $lensPowerId): array => [
                'id' => $lensPowerId === '' ? null : (int) $lensPowerId,
                'value' => $lensPowerId === '' ? null : $values[$lensPowerId],
                'available_quantity' => round(((float) $pieces - (float) ($sold[$lensPowerId] ?? 0)) / self::PIECES_IN_A_DOZEN, 2),
            ])
            ->filter(fn (array $power): bool => $power['available_quantity'] > 0.0001)
            ->sortBy(fn (array $power): float => $power['value'] === null ? PHP_FLOAT_MAX : (float) $power['value'])
            ->values()
            ->all();
    }

    /**
     * What came in of this item at each lens power, for its page: for every power its stock batches carry, the packets and pieces
     * that arrived (on the invoices both sides confirmed) and that are on their way (on the invoices only the importer confirmed),
     * and the pieces left of it (what arrived of it minus what was sold of it), from the lowest power to the highest,
     * with the pieces without a power last. The packets of a batch are its pieces over the pieces in a packet of its line.
     * Deleted lines and the lines of deleted invoices are not counted.
     *
     * @return Collection<int, array{power: ?string, arrived_packets: float, arrived_pieces: float, incoming_packets: float, incoming_pieces: float, remaining_pieces: float}>
     */
    public function quantitiesByPower(): Collection
    {
        $packetsOf = fn (Collection $batches): float => (float) $batches->sum(
            fn (Batch $batch): float => $batch->invoiceItem->pieces_per_packet > 0 ? $batch->quantity / $batch->invoiceItem->pieces_per_packet : 0.0,
        );

        return Batch::query()
            ->where('item_id', $this->id)
            ->whereHas('invoice', fn (Builder $query): Builder => $query
                ->whereIn('status', [InvoiceStatus::Confirmed, InvoiceStatus::ImporterConfirmed]))
            ->with(['invoice', 'invoiceItem', 'lensPower'])
            ->get()
            ->groupBy(fn (Batch $batch): string => (string) $batch->lens_power_id)
            ->map(function (Collection $batches) use ($packetsOf): array {
                [$arrived, $incoming] = $batches->partition(fn (Batch $batch): bool => $batch->invoice->status === InvoiceStatus::Confirmed);

                $lensPowerId = $batches->first()->lens_power_id;

                return [
                    'power' => $batches->first()->lensPower?->value,
                    'arrived_packets' => $packetsOf($arrived),
                    'arrived_pieces' => (float) $arrived->sum('quantity'),
                    'incoming_packets' => $packetsOf($incoming),
                    'incoming_pieces' => (float) $incoming->sum('quantity'),
                    'remaining_pieces' => $this->availablePiecesOfPower($lensPowerId === null ? null : (int) $lensPowerId),
                ];
            })
            ->sortBy(fn (array $row): float => $row['power'] === null ? PHP_FLOAT_MAX : (float) $row['power'])
            ->values();
    }

    /**
     * The colors in one packet written one way only: each color with its pieces, from the lowest color, so the same mix
     * of colors is always the same list. A packet without colors is an empty list.
     *
     * @param  array<int, array{color_id: mixed, pieces: mixed}>|null  $colors
     * @return list<array{color_id: int, pieces: float}>
     */
    public static function colorMix(?array $colors): array
    {
        return collect($colors ?? [])
            ->map(fn (array $color): array => ['color_id' => (int) $color['color_id'], 'pieces' => (float) $color['pieces']])
            ->sortBy('color_id')
            ->values()
            ->all();
    }

    /**
     * A packet type as a key: its lens power and the mix of colors in its packets, such as "3|5:10,7:10".
     * Packets of the same power and the same mix are the same type, whichever purchase they came on.
     *
     * @param  array<int, array{color_id: mixed, pieces: mixed}>|null  $colors
     */
    public static function packetTypeKey(?int $lensPowerId, ?array $colors): string
    {
        return $lensPowerId.'|'.collect(self::colorMix($colors))
            ->map(fn (array $color): string => $color['color_id'].':'.$color['pieces'])
            ->implode(',');
    }

    /**
     * The pieces left of one packet type of this item: of its packets at this lens power with this mix of colors, what came in
     * on the purchase invoices both sides confirmed, minus what was sold of that type on the orders that are not deleted.
     * The seller chooses the type, and a sale takes only from it.
     *
     * @param  array<int, array{color_id: mixed, pieces: mixed}>|null  $colors
     */
    public function availablePiecesOfPacketType(?int $lensPowerId, ?array $colors): float
    {
        $key = self::packetTypeKey($lensPowerId, $colors);

        return (float) ($this->piecesBoughtByPacketType()->get($key)['pieces'] ?? 0)
            - (float) ($this->piecesSoldByPacketType()->get($key) ?? 0);
    }

    /**
     * Whether this item came in with mixes of colors at one lens power (or without a power when it is null), on a purchase invoice
     * both sides confirmed. Such packets are sold by choosing their colors.
     */
    public function hasColorMixesAt(?int $lensPowerId): bool
    {
        return $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)
            ->where('lens_power_id', $lensPowerId)
            ->whereHas('colors')
            ->exists();
    }

    /**
     * The packet types the seller can choose from: every lens power with every mix of colors its packets came in, with the whole
     * packets left of it and their dozens, from the lowest power (none last), the oldest mix first. A type without a whole packet
     * left is not listed, and an item without a packet size has none, because it is not sold.
     *
     * @return list<array{lens_power_id: ?int, colors: list<array{id: int, name: ?string, pieces: float}>, available_packets: int, available_quantity: float}>
     */
    public function packetTypesLeft(): array
    {
        $piecesPerPacket = (float) $this->pieces_per_packet;

        if ($piecesPerPacket <= 0) {
            return [];
        }

        $sold = $this->piecesSoldByPacketType();
        $names = Color::withTrashed()->pluck('name', 'id');
        $powers = LensPower::withTrashed()->pluck('value', 'id');

        return $this->piecesBoughtByPacketType()
            ->map(function (array $type, string $key) use ($sold, $piecesPerPacket, $names): array {
                $pieces = $type['pieces'] - (float) ($sold->get($key) ?? 0);

                return [
                    ...$type,
                    'available_packets' => (int) floor(($pieces + 0.0001) / $piecesPerPacket),
                    'available_quantity' => round($pieces / self::PIECES_IN_A_DOZEN, 2),
                    'colors' => collect($type['colors'])
                        ->map(fn (array $color): array => ['id' => $color['color_id'], 'name' => $names[$color['color_id']] ?? null, 'pieces' => $color['pieces']])
                        ->all(),
                ];
            })
            ->filter(fn (array $type): bool => $type['available_packets'] >= 1)
            ->sortBy([
                fn (array $first, array $second): int => ($powers[$first['lens_power_id']] ?? PHP_FLOAT_MAX) <=> ($powers[$second['lens_power_id']] ?? PHP_FLOAT_MAX),
                fn (array $first, array $second): int => $first['first_batch_id'] <=> $second['first_batch_id'],
            ])
            ->map(fn (array $type): array => Arr::only($type, ['lens_power_id', 'colors', 'available_packets', 'available_quantity']))
            ->values()
            ->all();
    }

    /**
     * The pieces that came in of each packet type, on the purchase invoices both sides confirmed, keyed by the type:
     * each with its lens power, the mix of colors in its packets, and the first batch it came in, to keep the oldest first.
     *
     * @return Collection<string, array{lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: float, first_batch_id: int}>
     */
    protected function piecesBoughtByPacketType(): Collection
    {
        return $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)
            ->with('invoiceItem.colors')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Batch $batch): string => self::packetTypeKey($batch->lens_power_id === null ? null : (int) $batch->lens_power_id, $this->colorsOfBatch($batch)))
            ->map(fn (Collection $batches): array => [
                'lens_power_id' => $batches->first()->lens_power_id === null ? null : (int) $batches->first()->lens_power_id,
                'colors' => self::colorMix($this->colorsOfBatch($batches->first())),
                'pieces' => (float) $batches->sum('quantity'),
                'first_batch_id' => (int) $batches->first()->id,
            ]);
    }

    /**
     * The pieces sold of each packet type on the orders that are not deleted, keyed by the type.
     *
     * @return Collection<string, float>
     */
    protected function piecesSoldByPacketType(): Collection
    {
        return OrderItem::query()
            ->where('item_id', $this->id)
            ->whereHas('order')
            ->get(['lens_power_id', 'colors', 'pieces'])
            ->groupBy(fn (OrderItem $line): string => self::packetTypeKey($line->lens_power_id === null ? null : (int) $line->lens_power_id, $line->colors))
            ->map(fn (Collection $lines): float => (float) $lines->sum('pieces'));
    }

    /**
     * The mix of colors in one packet of a batch: the colors of its line, which are the same for all the line's packets,
     * or none for a line without colors.
     *
     * @return list<array{color_id: int, pieces: float}>
     */
    protected function colorsOfBatch(Batch $batch): array
    {
        return self::colorMix($batch->invoiceItem?->colors->map(fn (InvoiceItemColor $color): array => [
            'color_id' => $color->color_id,
            'pieces' => $color->pieces,
        ])->all());
    }

    /**
     * What is left of one color of this item at one lens power (or without a power when it is null), in pieces, for its page:
     * the pieces of the color that came in at that power on the purchase invoices both sides confirmed, minus the pieces of it
     * that left in the packets sold there. A sold packet takes its own colors with it.
     */
    public function availablePiecesOfColor(?int $lensPowerId, int $colorId): float
    {
        $bought = (float) BatchColor::query()
            ->where('color_id', $colorId)
            ->whereIn('batch_id', $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)->where('lens_power_id', $lensPowerId)->select('id'))
            ->sum('pieces');

        $sold = OrderItem::query()
            ->where('item_id', $this->id)
            ->where('lens_power_id', $lensPowerId)
            ->whereNotNull('colors')
            ->whereHas('order')
            ->get(['colors', 'pieces'])
            ->sum(function (OrderItem $line) use ($colorId): float {
                $mix = collect(self::colorMix($line->colors));
                $packet = (float) $mix->sum('pieces');

                return $packet > 0 ? (float) $line->pieces * (float) $mix->where('color_id', $colorId)->sum('pieces') / $packet : 0.0;
            });

        return $bought - $sold;
    }

    /**
     * What came in of this item in each color at each lens power, for its page, in pieces: the pieces that arrived (on the invoices
     * both sides confirmed) and that are on their way (on the invoices only the importer confirmed), and the pieces left
     * (what arrived minus what left in the packets sold), from the lowest power (the pieces without a power last), then by color.
     * Deleted lines and the lines of deleted invoices are not counted.
     *
     * @return Collection<int, array{power: ?string, color: ?string, arrived_pieces: float, incoming_pieces: float, remaining_pieces: float}>
     */
    public function quantitiesByColor(): Collection
    {
        $batches = Batch::query()
            ->where('item_id', $this->id)
            ->whereHas('invoice', fn (Builder $query): Builder => $query
                ->whereIn('status', [InvoiceStatus::Confirmed, InvoiceStatus::ImporterConfirmed]));

        return $this->colorRowsOf($batches)
            ->groupBy(fn (array $row): string => $row['lens_power_id'].'|'.$row['color_id'])
            ->sortBy(fn (Collection $rows): string => $rows->first()['sort'])
            ->map(fn (Collection $rows): array => [
                'power' => $rows->first()['power'],
                'color' => $rows->first()['color'],
                'arrived_pieces' => (float) $rows->where('arrived', true)->sum('pieces'),
                'incoming_pieces' => (float) $rows->where('arrived', false)->sum('pieces'),
                'remaining_pieces' => $this->availablePiecesOfColor($rows->first()['lens_power_id'], $rows->first()['color_id']),
            ])
            ->values();
    }

    /**
     * The pieces of each color in the given batches, one row for each color of each batch, with whether its invoice arrived
     * (both sides confirmed it) and a key that sorts it by power (none last) and then by color name.
     *
     * @param  Builder<Batch>  $batches
     * @return Collection<int, array{lens_power_id: ?int, power: ?string, color_id: int, color: ?string, pieces: float, arrived: bool, sort: string}>
     */
    protected function colorRowsOf(Builder $batches): Collection
    {
        return $batches
            ->with(['invoice', 'lensPower', 'colors.color' => fn ($query) => $query->withTrashed()])
            ->get()
            ->flatMap(fn (Batch $batch): Collection => $batch->colors->map(fn (BatchColor $color): array => [
                'lens_power_id' => $batch->lens_power_id === null ? null : (int) $batch->lens_power_id,
                'power' => $batch->lensPower?->value,
                'color_id' => (int) $color->color_id,
                'color' => $color->color?->name,
                'pieces' => (float) $color->pieces,
                'arrived' => $batch->invoice->status === InvoiceStatus::Confirmed,
                'sort' => sprintf('%012.2f|%s', $batch->lensPower === null ? 999999999 : (float) $batch->lensPower->value, $color->color?->name),
            ]));
    }

    /**
     * What one piece comes to at this price of one dozen: the dozen's price ÷ the pieces in a dozen. It is only shown, never saved.
     */
    public static function piecePrice(float $dozenPrice): float
    {
        return $dozenPrice / self::PIECES_IN_A_DOZEN;
    }

    /**
     * Writes the available quantity again, from the beginning. Every change on the orders calls this, and nothing else:
     * it reads all the sales and all the purchases confirmed by both sides, and writes the number.
     * Nothing in the system adds to the number or takes from it, so it never drifts.
     */
    public function recalculateAvailableQuantity(): void
    {
        $this->available_quantity = $this->availableQuantity();

        $this->saveQuietly();
    }

    /**
     * Writes the available quantity, the incoming quantity, and the cost again, from the beginning.
     * Every change on the purchase side calls this, because a purchase can touch the three of them.
     */
    public function recalculateStock(): void
    {
        $costPrice = $this->costPriceOfTheLastPurchase();

        if (abs($costPrice - (float) $this->cost_price) > 0.0001) {
            $this->last_cost_price = (float) $this->cost_price;
        }

        $this->available_quantity = $this->availableQuantity();
        $this->incoming_quantity = $this->incomingQuantity();
        $this->cost_price = $costPrice;

        $this->saveQuietly();
    }

    /**
     * Everything sold of this item, in dozens: the quantity of its lines on the orders that are not deleted.
     */
    public function soldQuantity(): float
    {
        return (float) OrderItem::query()
            ->where('item_id', $this->id)
            ->whereHas('order')
            ->sum('quantity');
    }

    /**
     * What one dozen of this item cost on average across everything bought of it: all the money paid for it
     * over all the pieces bought, × the pieces in a dozen, so a big purchase weighs more than a small one.
     * Only the purchases both sides confirmed are counted, like everywhere else.
     */
    public function averagePurchasePrice(): float
    {
        $batches = $this->purchases()->get();
        $quantity = (float) $batches->sum('quantity');

        if ($quantity <= 0) {
            return 0.0;
        }

        return round($batches->sum(fn (Batch $batch): float => (float) $batch->quantity * (float) $batch->purchase_price) / $quantity * self::PIECES_IN_A_DOZEN, 2);
    }

    /**
     * The most this item was ever bought for, per dozen.
     */
    public function highestPurchasePrice(): float
    {
        return round((float) $this->purchases()->max('purchase_price') * self::PIECES_IN_A_DOZEN, 2);
    }

    /**
     * The least this item was ever bought for, per dozen.
     */
    public function lowestPurchasePrice(): float
    {
        return round((float) $this->purchases()->min('purchase_price') * self::PIECES_IN_A_DOZEN, 2);
    }

    /**
     * The purchases of this item that count: the batches of the invoices both sides confirmed, of a real quantity and price.
     *
     * @return Builder<Batch>
     */
    protected function purchases(): Builder
    {
        return $this->batchesOfInvoicesWith(InvoiceStatus::Confirmed)
            ->where('quantity', '>', 0)
            ->where('purchase_price', '>', 0);
    }

    /**
     * The price of a dozen on the last batch that came in on an invoice both sides confirmed: its piece price × 12, rounded to two decimals.
     * A batch without a quantity or without a price, such as a return, does not set the cost. Without any batch the cost is zero.
     */
    protected function costPriceOfTheLastPurchase(): float
    {
        $batch = $this->purchases()
            ->orderByDesc('invoice_id')
            ->orderByDesc('id')
            ->first();

        return $batch === null ? 0.0 : round((float) $batch->purchase_price * self::PIECES_IN_A_DOZEN, 2);
    }

    /**
     * This item's batches that came in on invoices of the given status. Deleted batches and deleted invoices are left out.
     *
     * @return Builder<Batch>
     */
    protected function batchesOfInvoicesWith(InvoiceStatus $status): Builder
    {
        return Batch::query()
            ->where('item_id', $this->id)
            ->whereHas('invoice', fn (Builder $query): Builder => $query->where('status', $status));
    }

    /**
     * The profit margin: the sale price minus the cost price. It is not stored.
     * The cost price is read only for users: it is not fillable, so only the system sets it.
     */
    public function profitMargin(): float
    {
        return (float) $this->sale_price - (float) $this->cost_price;
    }

    /**
     * Creates the item's specific description, or renames it to the item's name.
     * It is saved without an audit, because it is a copy of the item.
     */
    protected function syncSpecificDescription(): void
    {
        $description = $this->specificDescription()->withTrashed()->first();

        if ($description === null) {
            (new SpecificDescription([
                'general_description_id' => GeneralDescription::purchases()->id,
                'item_id' => $this->id,
                'name' => $this->name,
            ]))->saveQuietly();

            return;
        }

        if ($description->name !== $this->name) {
            $description->name = $this->name;
            $description->saveQuietly();
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
            'is_active' => 'boolean',
            'sale_price' => 'float',
            'pieces_per_packet' => 'integer',
            'cost_price' => 'float',
            'last_cost_price' => 'float',
            'available_quantity' => 'float',
            'incoming_quantity' => 'float',
        ];
    }
}
