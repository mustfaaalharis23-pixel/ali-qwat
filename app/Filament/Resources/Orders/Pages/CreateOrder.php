<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderPaymentType;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Color;
use App\Models\Item;
use App\Models\LensPower;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * The panel's sale, built the way the app's checkout is: the seller searches the items, chooses a power and the colors of its
 * packets on an item's card, and adds whole packets with + and −; the cart beside them shows each line with its pieces, its
 * dozens at the price of a dozen, and the total, and makes the sale through the same checkout as the app.
 */
class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * The page uses the full width, so the items and the cart sit side by side.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * The name the seller searches the items by.
     */
    public string $itemSearch = '';

    /**
     * The sale being built, keyed by the item and its packet type ("item#power|colors"): each line an item at a power with the
     * colors of its packets, and its whole packets.
     *
     * @var array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, packets: int}>
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
            $this->chosenTypes[$itemId.'|'.$this->powerKey($this->powerOf($item, $item->powersLeft()))] = $typeKey;
        }
    }

    /**
     * Adds one packet of the type the item's card is on, or tells the seller nothing more is left of it.
     */
    public function addPacket(int $itemId): void
    {
        $item = Item::query()->find($itemId);
        $type = $item === null ? null : $this->typeOf($item, $this->powerOf($item, $item->powersLeft()));

        if ($item === null || $type === null) {
            $this->notifyNotEnough(0, 0);

            return;
        }

        $key = $item->id.'#'.$type['key'];
        $packets = ($this->cart[$key]['packets'] ?? 0) + 1;

        if ($packets > $type['available_packets']) {
            $this->notifyNotEnough($type['available_packets'], $type['available_quantity']);

            return;
        }

        $this->cart[$key] = [
            'item_id' => $item->id,
            'lens_power_id' => $type['lens_power_id'],
            'colors' => array_map(fn (array $color): array => ['color_id' => $color['id'], 'pieces' => (float) $color['pieces']], $type['colors']),
            'packets' => $packets,
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

    public function incrementLine(string $key): void
    {
        $this->setPackets($key, ($this->cart[$key]['packets'] ?? 0) + 1);
    }

    public function decrementLine(string $key): void
    {
        $this->setPackets($key, ($this->cart[$key]['packets'] ?? 0) - 1);
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
     * The items the seller searches, as the cards show them: the active items with a packet size, the ones in stock first, each
     * with its powers and the color mixes at the power it is on, the type it is on, and the packets of it already in the cart.
     *
     * @return list<array<string, mixed>>
     */
    public function checkoutItems(): array
    {
        return Item::query()
            ->active()
            ->whereNotNull('pieces_per_packet')
            ->when(filled($this->itemSearch), fn ($query) => $query->whereLike('name', '%'.trim($this->itemSearch).'%'))
            ->orderByRaw('available_quantity <= 0')
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (Item $item): array => $this->itemCard($item))
            ->all();
    }

    /**
     * The cart as its panel shows it: each line with its item, power, colors, packets, pieces, dozens, price of a dozen, and total,
     * and the total of the sale. The totals come from the pieces, so a dozen that is not whole never shifts them.
     *
     * @return array{lines: list<array<string, mixed>>, total: float, totalText: string}
     */
    public function cartSummary(): array
    {
        $items = Item::query()->whereIn('id', array_column($this->cart, 'item_id'))->get()->keyBy('id');
        $powers = LensPower::withTrashed()->whereIn('id', array_filter(array_column($this->cart, 'lens_power_id')))->pluck('value', 'id');
        $colorNames = Color::withTrashed()->pluck('name', 'id');

        $lines = collect($this->cart)
            ->filter(fn (array $line): bool => $items->has($line['item_id']))
            ->map(function (array $line, string $key) use ($items, $powers, $colorNames): array {
                $item = $items[$line['item_id']];
                $pieces = $line['packets'] * (int) $item->pieces_per_packet;
                $price = (float) $item->sale_price;

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
                    'total' => $pieces * $price / Item::PIECES_IN_A_DOZEN,
                    'totalText' => $this->number($pieces * $price / Item::PIECES_IN_A_DOZEN),
                ];
            })
            ->values();

        return [
            'lines' => $lines->all(),
            'total' => (float) $lines->sum('total'),
            'totalText' => $this->number((float) $lines->sum('total')),
        ];
    }

    /**
     * An item as its card shows it.
     *
     * @return array<string, mixed>
     */
    protected function itemCard(Item $item): array
    {
        $powers = $item->powersLeft();
        $powerId = $this->powerOf($item, $powers);
        $types = $this->typesAt($item, $powerId);
        $type = $this->typeOf($item, $powerId, $types);
        $inCart = $type === null ? null : ($this->cart[$item->id.'#'.$type['key']] ?? null);
        $pieces = $inCart === null ? 0 : $inCart['packets'] * (int) $item->pieces_per_packet;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'image' => $this->imageOf($item),
            'price' => $this->number((float) $item->sale_price),
            'piecesPerPacket' => (int) $item->pieces_per_packet,
            'isOutOfStock' => (float) $item->available_quantity <= 0 || $types === [],
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
            'dozensLeft' => $this->number($type['available_quantity'] ?? (float) $item->available_quantity),
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
     * The packet types of the item at a power, each with its key.
     *
     * @return list<array{lens_power_id: ?int, colors: list<array{id: int, name: ?string, pieces: float}>, available_packets: int, available_quantity: float, key: string}>
     */
    protected function typesAt(Item $item, ?int $lensPowerId): array
    {
        return collect($item->packetTypesLeft())
            ->filter(fn (array $type): bool => $type['lens_power_id'] === $lensPowerId)
            ->map(fn (array $type): array => [
                ...$type,
                'key' => Item::packetTypeKey($type['lens_power_id'], array_map(fn (array $color): array => ['color_id' => $color['id'], 'pieces' => $color['pieces']], $type['colors'])),
            ])
            ->values()
            ->all();
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

    /**
     * The order is saved through the checkout, from the cart, so the prices come from the items and the sale rules are applied.
     * An empty cart, or a rule the checkout rejects, is shown as a notification and the form stays open.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        if ($this->cart === []) {
            Notification::make()->warning()->title(__('filament/resources/order.checkout.cart_empty'))->send();

            $this->halt();
        }

        $checkoutData = new CheckoutData(
            customerId: filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null,
            // The select hands over the payment type as the enum itself.
            paymentType: ($data['payment_type'] ?? null) instanceof OrderPaymentType
                ? $data['payment_type']
                : OrderPaymentType::tryFrom((string) ($data['payment_type'] ?? '')),
            date: $data['date'],
            lines: array_values(array_map(
                fn (array $line): CheckoutLine => new CheckoutLine(
                    $line['item_id'],
                    lensPowerId: $line['lens_power_id'],
                    packets: $line['packets'],
                    colors: $line['colors'],
                ),
                $this->cart,
            )),
            notes: $data['notes'] ?? null,
            downPayment: filled($data['down_payment'] ?? null) ? (float) $data['down_payment'] : null,
            weeklyPayment: filled($data['weekly_payment'] ?? null) ? (float) $data['weekly_payment'] : null,
        );

        try {
            return app(Checkout::class)->handle(auth()->user(), $checkoutData);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first())
                ->send();

            $this->halt();
        }
    }
}
