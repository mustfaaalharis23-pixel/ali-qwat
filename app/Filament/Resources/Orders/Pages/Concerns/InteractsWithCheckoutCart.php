<?php

namespace App\Filament\Resources\Orders\Pages\Concerns;

use App\Enums\OrderPaymentType;
use App\Models\Color;
use App\Models\Item;
use App\Models\LensPower;
use App\Orders\Checkout\CheckoutLine;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * The cart of the panel's sale, built the way the app's checkout is: the seller searches the items, chooses a power and the colors of
 * its packets on an item's card, and types the whole packets; the cart beside them shows each line with its pieces, its
 * dozens at the price of a dozen, its discount (an amount, or a percent, off the line's total), and the total. A new sale and the edit of a
 * saved order share it. On an edit, the order's own
 * lines are counted as available to it, because their pieces are already out of the stock, and they keep the price they were sold at.
 */
trait InteractsWithCheckoutCart
{
    /**
     * The name the seller searches the items by.
     */
    public string $itemSearch = '';

    /**
     * The sale being built, keyed by the item and its packet type ("item#power|colors"): each line an item at a power with the
     * colors of its packets, its whole packets, and its discount off the line's total: the amount typed, or the percent typed
     * (null when the seller types an amount). A percent follows the line: change its packets and its discount changes with them.
     *
     * @var array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, packets: int, discount?: float, discount_percent?: ?float}>
     */
    public array $cart = [];

    /**
     * The power the seller is on for each item, by item id: a power's id, or "none" for the packets without a power.
     *
     * @var array<string, int|string>
     */
    public array $chosenPowers = [];

    /**
     * The packet type the seller is on for each item at each power, keyed by "item|power".
     *
     * @var array<string, string>
     */
    public array $chosenTypes = [];

    /**
     * The lines the order already has saved, keyed like the cart, with their pieces, the price they were sold at, and their discount.
     * A new sale has none.
     *
     * @return array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: int, packets: int, price: float, discount: float}>
     */
    protected function linesOfThisOrder(): array
    {
        return [];
    }

    /**
     * The sale is confirmed from the cart, so under the form only leaving the page is left.
     *
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [$this->getCancelFormAction()];
    }

    /**
     * Puts the item's card on a power: its color mixes, its stock, and its + are then the power's.
     */
    public function choosePower(int $itemId, ?int $lensPowerId): void
    {
        $this->chosenPowers[(string) $itemId] = $lensPowerId ?? 'none';
    }

    /**
     * Puts the item's card on a packet type at its power.
     */
    public function chooseType(int $itemId, string $typeKey): void
    {
        $item = Item::query()->find($itemId);

        if ($item !== null) {
            $this->chosenTypes[$itemId.'|'.$this->powerKey($this->powerOf($item, $this->powersOf($item)))] = $typeKey;
        }
    }

    /**
     * Adds the packets the seller typed (one when nothing is typed) of the type the item's card is on, never past what is
     * left of it: past that, the seller is told and what is left is added.
     */
    public function addPackets(int $itemId, mixed $packets = 1): void
    {
        $item = Item::query()->find($itemId);
        $type = $item === null ? null : $this->typeOf($item, $this->powerOf($item, $this->powersOf($item)));

        if ($item === null || $type === null) {
            $this->notifyNotEnough(0, 0);

            return;
        }

        $key = $item->id.'#'.$type['key'];
        $packets = ($this->cart[$key]['packets'] ?? 0) + max(1, (int) $packets);

        if ($packets > $type['available_packets']) {
            $this->notifyNotEnough($type['available_packets'], $type['available_quantity']);
            $packets = $type['available_packets'];
        }

        if ($packets <= 0) {
            return;
        }

        $this->cart[$key] = [
            'item_id' => $item->id,
            'lens_power_id' => $type['lens_power_id'],
            'colors' => array_map(fn (array $color): array => ['color_id' => $color['id'], 'pieces' => (float) $color['pieces']], $type['colors']),
            'packets' => $packets,
            'discount' => $this->cart[$key]['discount'] ?? 0.0,
            'discount_percent' => $this->cart[$key]['discount_percent'] ?? null,
        ];
    }

    /**
     * Sets a line's packets, never past what is left of its type. Zero or less takes the line out.
     */
    public function setPackets(string $key, mixed $packets): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        $packets = (int) $packets;

        if ($packets <= 0) {
            unset($this->cart[$key]);

            return;
        }

        $line = $this->cart[$key];
        $left = $this->packetsLeftOf($line);

        if ($packets > $left['packets']) {
            $this->notifyNotEnough($left['packets'], $left['dozens']);
            $packets = $left['packets'];
        }

        $this->cart[$key]['packets'] = $packets;
    }

    /**
     * Sets a line's discount as one amount off the line's total. Empty or below zero is no discount. The checkout checks it against
     * the line when the sale is confirmed.
     */
    public function setDiscount(string $key, mixed $discount): void
    {
        if (isset($this->cart[$key])) {
            $this->cart[$key]['discount'] = max(0.0, (float) $discount);
            $this->cart[$key]['discount_percent'] = null;
        }
    }

    /**
     * Sets a line's discount as a percent of the line's total, from 0 to 100.
     */
    public function setDiscountPercent(string $key, mixed $percent): void
    {
        if (isset($this->cart[$key])) {
            $this->cart[$key]['discount_percent'] = min(100.0, max(0.0, (float) $percent));
        }
    }

    /**
     * Turns a line's discount into an amount or into a percent, keeping what it takes off: 1,500 off 15,000 becomes 10%, and back.
     */
    public function setDiscountMode(string $key, string $mode): void
    {
        $line = $this->cart[$key] ?? null;
        $item = $line === null ? null : Item::query()->find($line['item_id']);

        if ($item === null) {
            return;
        }

        $total = $this->lineTotal($line, $key, $item);
        $discount = $this->discountOf($line, $total);

        if ($mode === 'percent' && ($line['discount_percent'] ?? null) === null) {
            $this->cart[$key]['discount_percent'] = $total > 0 ? round($discount / $total * 100, 2) : 0.0;
        }

        if ($mode === 'amount') {
            $this->cart[$key]['discount'] = $discount;
            $this->cart[$key]['discount_percent'] = null;
        }
    }

    public function removeLine(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
    }

    /**
     * The items the seller searches, as the cards show them: the active items with a packet size, the order's own items first on an
     * edit, then the ones in stock, each with its powers and the color mixes at the power it is on, the type it is on, and the
     * packets of it already in the cart.
     *
     * @return list<array<string, mixed>>
     */
    public function checkoutItems(): array
    {
        $ownItemIds = array_values(array_unique(array_map('intval', array_column($this->linesOfThisOrder(), 'item_id'))));

        return Item::query()
            ->active()
            ->whereNotNull('pieces_per_packet')
            ->when(filled($this->itemSearch), fn ($query) => $query->whereLike('name', '%'.trim($this->itemSearch).'%'))
            ->when($ownItemIds !== [], fn ($query) => $query->orderByRaw('CASE WHEN id IN ('.implode(',', $ownItemIds).') THEN 0 ELSE 1 END'))
            ->orderByRaw('available_quantity <= 0')
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (Item $item): array => $this->itemCard($item))
            ->all();
    }

    /**
     * The cart as its panel shows it: each line with its item, power, colors, packets, pieces, dozens, price of a dozen, total,
     * discount (and the percent it was typed as), and what it comes to after the discount, and the sale's total before its discounts, its discounts, and what it
     * comes to. A line the order already had keeps the price it was sold at; any other takes its item's sale price.
     * The totals come from the pieces, so a dozen that is not whole never shifts them.
     *
     * @return array{lines: list<array<string, mixed>>, subtotal: float, subtotalText: string, discount: float, discountText: string, total: float, totalText: string}
     */
    public function cartSummary(): array
    {
        $items = Item::query()->whereIn('id', array_column($this->cart, 'item_id'))->get()->keyBy('id');
        $powers = LensPower::withTrashed()->whereIn('id', array_filter(array_column($this->cart, 'lens_power_id')))->pluck('value', 'id');
        $colorNames = Color::withTrashed()->pluck('name', 'id');
        $savedLines = $this->linesOfThisOrder();

        $lines = collect($this->cart)
            ->filter(fn (array $line): bool => $items->has($line['item_id']))
            ->map(function (array $line, string $key) use ($items, $powers, $colorNames, $savedLines): array {
                $item = $items[$line['item_id']];
                $pieces = $line['packets'] * (int) $item->pieces_per_packet;
                $price = (float) ($savedLines[$key]['price'] ?? $item->sale_price);
                $total = $this->lineTotal($line, $key, $item);
                $discount = $this->discountOf($line, $total);
                $percent = $line['discount_percent'] ?? null;

                return [
                    'key' => $key,
                    'name' => $item->name,
                    'image' => $this->imageOf($item),
                    'power' => $line['lens_power_id'] === null ? null : ($powers[$line['lens_power_id']] ?? null),
                    'colors' => array_map(fn (array $color): array => [
                        'name' => (string) ($colorNames[$color['color_id']] ?? ''),
                        'pieces' => $this->number($color['pieces']),
                    ], $line['colors']),
                    'packets' => $line['packets'],
                    'breakdown' => __('filament/resources/order.checkout.breakdown', [
                        'pieces' => $this->number($pieces),
                        'dozens' => $this->number($pieces / Item::PIECES_IN_A_DOZEN),
                        'price' => $this->number($price),
                    ]),
                    'total' => $total,
                    'totalText' => $this->number($total),
                    'discount' => $discount,
                    'discountText' => $this->number($discount),
                    'discountPercent' => $percent,
                    'net' => $total - $discount,
                    'netText' => $this->number($total - $discount),
                ];
            })
            ->values();

        $subtotal = (float) $lines->sum('total');
        $discount = (float) $lines->sum('discount');

        return [
            'lines' => $lines->all(),
            'subtotal' => $subtotal,
            'subtotalText' => $this->number($subtotal),
            'discount' => $discount,
            'discountText' => $this->number($discount),
            'total' => $subtotal - $discount,
            'totalText' => $this->number($subtotal - $discount),
        ];
    }

    /**
     * The cart's lines as the checkout takes them, each with the amount its discount takes off.
     *
     * @return list<CheckoutLine>
     */
    protected function checkoutLines(): array
    {
        $items = Item::query()->whereIn('id', array_column($this->cart, 'item_id'))->get()->keyBy('id');

        return array_values(array_map(
            fn (array $line, string $key): CheckoutLine => new CheckoutLine(
                $line['item_id'],
                lensPowerId: $line['lens_power_id'],
                packets: $line['packets'],
                colors: $line['colors'],
                discount: $items->has($line['item_id']) ? $this->discountOf($line, $this->lineTotal($line, $key, $items[$line['item_id']])) : 0.0,
            ),
            $this->cart,
            array_keys($this->cart),
        ));
    }

    /**
     * A cart line's total before its discount: its pieces in dozens at the price its saved line was sold at on an edit, else at its
     * item's sale price.
     *
     * @param  array{item_id: int, packets: int}  $line
     */
    protected function lineTotal(array $line, string $key, Item $item): float
    {
        $price = (float) ($this->linesOfThisOrder()[$key]['price'] ?? $item->sale_price);

        return $line['packets'] * (int) $item->pieces_per_packet * $price / Item::PIECES_IN_A_DOZEN;
    }

    /**
     * What a cart line's discount takes off its total: the percent typed, of the total, to a whole dinar; else the amount typed.
     *
     * @param  array{discount?: float, discount_percent?: ?float}  $line
     */
    protected function discountOf(array $line, float $total): float
    {
        $percent = $line['discount_percent'] ?? null;

        return $percent === null ? (float) ($line['discount'] ?? 0) : round($total * (float) $percent / 100);
    }

    /**
     * The payment type the select hands over: the enum itself once chosen, or its value.
     */
    protected function paymentTypeFrom(mixed $paymentType): ?OrderPaymentType
    {
        return $paymentType instanceof OrderPaymentType ? $paymentType : OrderPaymentType::tryFrom((string) ($paymentType ?? ''));
    }

    /**
     * An item as its card shows it.
     *
     * @return array<string, mixed>
     */
    protected function itemCard(Item $item): array
    {
        $powers = $this->powersOf($item);
        $powerId = $this->powerOf($item, $powers);
        $types = $this->typesAt($item, $powerId);
        $type = $this->typeOf($item, $powerId, $types);
        $inCart = $type === null ? null : ($this->cart[$item->id.'#'.$type['key']] ?? null);
        $pieces = $inCart === null ? 0 : $inCart['packets'] * (int) $item->pieces_per_packet;
        $dozensLeft = (float) $item->available_quantity + $this->ownPiecesOf($item) / Item::PIECES_IN_A_DOZEN;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'image' => $this->imageOf($item),
            'price' => $this->number((float) $item->sale_price),
            'piecesPerPacket' => (int) $item->pieces_per_packet,
            'isOutOfStock' => $dozensLeft <= 0 || $types === [],
            'powers' => array_map(fn (array $power): array => [
                'id' => $power['id'],
                'value' => $power['value'] ?? __('filament/resources/lens_power.none'),
                'dozens' => $this->number($power['available_quantity']),
                'isSelected' => $power['id'] === $powerId,
            ], $powers),
            'showsTypes' => collect($types)->contains(fn (array $type): bool => $type['colors'] !== []),
            'types' => array_map(fn (array $candidate): array => [
                'key' => $candidate['key'],
                'colors' => array_map(fn (array $color): array => ['name' => (string) $color['name'], 'pieces' => $this->number($color['pieces'])], $candidate['colors']),
                'packets' => $candidate['available_packets'],
                'dozens' => $this->number($candidate['available_quantity']),
                'isSelected' => $candidate['key'] === $type['key'],
            ], $types),
            'dozensLeft' => $this->number($type['available_quantity'] ?? $dozensLeft),
            'packetsLeft' => $type['available_packets'] ?? 0,
            'lineKey' => $type === null ? null : $item->id.'#'.$type['key'],
            'inCart' => $inCart['packets'] ?? 0,
            'inCartBreakdown' => $inCart === null ? null : __('filament/resources/order.checkout.pieces_and_dozens', [
                'pieces' => $this->number($pieces),
                'dozens' => $this->number($pieces / Item::PIECES_IN_A_DOZEN),
            ]),
        ];
    }

    /**
     * The lens powers the item can be sold at, with what is left of each in dozens: the powers left in the stock, and on an edit
     * the order's own pieces at each power added to them, from the lowest power (none last).
     *
     * @return list<array{id: ?int, value: ?string, available_quantity: float}>
     */
    protected function powersOf(Item $item): array
    {
        $powers = $item->powersLeft();
        $ownLines = $this->ownLinesOf($item);

        if ($ownLines === [] || ! $item->wasBoughtWithPowers()) {
            return $powers;
        }

        $powers = collect($powers)->keyBy(fn (array $power): string => $this->powerKey($power['id']));
        $values = LensPower::withTrashed()->whereIn('id', array_filter(array_column($ownLines, 'lens_power_id')))->pluck('value', 'id');

        foreach ($ownLines as $line) {
            $key = $this->powerKey($line['lens_power_id']);
            $power = $powers->get($key) ?? [
                'id' => $line['lens_power_id'],
                'value' => $line['lens_power_id'] === null ? null : ($values[$line['lens_power_id']] ?? null),
                'available_quantity' => 0.0,
            ];
            $power['available_quantity'] = round($power['available_quantity'] + $line['pieces'] / Item::PIECES_IN_A_DOZEN, 2);
            $powers->put($key, $power);
        }

        return $powers
            ->sortBy(fn (array $power): float => $power['value'] === null ? PHP_FLOAT_MAX : (float) $power['value'])
            ->values()
            ->all();
    }

    /**
     * The power the item's card is on: the one picked, else its lowest. Null for an item without powers, or for its packets
     * without a power.
     *
     * @param  list<array{id: ?int, value: ?string, available_quantity: float}>  $powers
     */
    protected function powerOf(Item $item, array $powers): ?int
    {
        if ($powers === []) {
            return null;
        }

        $chosen = $this->chosenPowers[(string) $item->id] ?? null;
        $ids = array_column($powers, 'id');

        if ($chosen === 'none' && in_array(null, $ids, true)) {
            return null;
        }

        if ($chosen !== null && in_array((int) $chosen, $ids, true)) {
            return (int) $chosen;
        }

        return $powers[0]['id'];
    }

    /**
     * The packet types of the item at a power, each with its key: the ones left in the stock, and on an edit the order's own
     * packets of each type added to them.
     *
     * @return list<array{lens_power_id: ?int, colors: list<array{id: int, name: ?string, pieces: float}>, available_packets: int, available_quantity: float, key: string}>
     */
    protected function typesAt(Item $item, ?int $lensPowerId): array
    {
        $types = collect($item->packetTypesLeft())
            ->filter(fn (array $type): bool => $type['lens_power_id'] === $lensPowerId)
            ->map(fn (array $type): array => [
                ...$type,
                'key' => Item::packetTypeKey($type['lens_power_id'], array_map(fn (array $color): array => ['color_id' => $color['id'], 'pieces' => $color['pieces']], $type['colors'])),
            ])
            ->keyBy('key');

        $colorNames = Color::withTrashed()->pluck('name', 'id');

        foreach ($this->ownLinesOf($item) as $line) {
            if ($line['lens_power_id'] !== $lensPowerId) {
                continue;
            }

            $key = Item::packetTypeKey($line['lens_power_id'], $line['colors']);
            $type = $types->get($key) ?? [
                'lens_power_id' => $lensPowerId,
                'colors' => array_map(fn (array $color): array => [
                    'id' => $color['color_id'],
                    'name' => $colorNames[$color['color_id']] ?? null,
                    'pieces' => $color['pieces'],
                ], $line['colors']),
                'available_packets' => 0,
                'available_quantity' => 0.0,
                'key' => $key,
            ];
            $type['available_packets'] += $line['packets'];
            $type['available_quantity'] = round($type['available_quantity'] + $line['pieces'] / Item::PIECES_IN_A_DOZEN, 2);
            $types->put($key, $type);
        }

        return $types->values()->all();
    }

    /**
     * The packet type the item's card is on at a power: the one picked, else the first. Null when nothing is left there.
     *
     * @param  list<array<string, mixed>>|null  $types
     * @return array<string, mixed>|null
     */
    protected function typeOf(Item $item, ?int $lensPowerId, ?array $types = null): ?array
    {
        $types ??= $this->typesAt($item, $lensPowerId);
        $chosen = $this->chosenTypes[$item->id.'|'.$this->powerKey($lensPowerId)] ?? null;

        return collect($types)->firstWhere('key', $chosen) ?? ($types[0] ?? null);
    }

    /**
     * What is left of a cart line's packet type, in packets and in dozens.
     *
     * @param  array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, packets: int}  $line
     * @return array{packets: int, dozens: float}
     */
    protected function packetsLeftOf(array $line): array
    {
        $item = Item::query()->find($line['item_id']);
        $type = $item === null ? null : collect($this->typesAt($item, $line['lens_power_id']))
            ->firstWhere('key', Item::packetTypeKey($line['lens_power_id'], $line['colors']));

        return ['packets' => (int) ($type['available_packets'] ?? 0), 'dozens' => (float) ($type['available_quantity'] ?? 0)];
    }

    /**
     * The order's saved lines of the item.
     *
     * @return list<array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: int, packets: int, price: float}>
     */
    protected function ownLinesOf(Item $item): array
    {
        return array_values(array_filter($this->linesOfThisOrder(), fn (array $line): bool => $line['item_id'] === $item->id));
    }

    /**
     * The pieces of the item the order already has saved.
     */
    protected function ownPiecesOf(Item $item): int
    {
        return (int) array_sum(array_column($this->ownLinesOf($item), 'pieces'));
    }

    protected function powerKey(?int $lensPowerId): string
    {
        return $lensPowerId === null ? 'none' : (string) $lensPowerId;
    }

    protected function imageOf(Item $item): ?string
    {
        return filled($item->image) ? Storage::disk('public')->url($item->image) : null;
    }

    protected function number(float|int $value): string
    {
        return (string) Number::format($value, maxPrecision: 2, locale: app()->getLocale());
    }

    /**
     * Tells the seller the stock ran short, with the packets and the dozens left of the type.
     */
    protected function notifyNotEnough(int $packets, float $dozens): void
    {
        Notification::make()
            ->warning()
            ->title(__('filament/resources/order.checkout.not_enough', ['packets' => $packets, 'dozens' => $this->number($dozens)]))
            ->send();
    }
}
