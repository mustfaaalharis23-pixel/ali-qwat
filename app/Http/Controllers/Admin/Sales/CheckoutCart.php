<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Filament\Resources\Orders\Pages\Concerns\InteractsWithCheckoutCart;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Orders\Checkout\CheckoutLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * The cart of a sale as the admin application posts it: the same cart as the panel's sale (InteractsWithCheckoutCart), filled from
 * the request instead of a Livewire page, so the item cards and the cart summary are worked out by the same code.
 * On the edit of an order, the order's own lines count as available to it and keep the price they were sold at, as on EditOrder.
 */
class CheckoutCart
{
    use InteractsWithCheckoutCart {
        itemCard as cardOfTheItem;
    }

    /**
     * The order's saved lines, read once.
     *
     * @var array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: int, packets: int, price: float}>|null
     */
    private ?array $savedLines = null;

    /**
     * The items of the cart with their stock, read once.
     *
     * @var Collection<int, Item>|null
     */
    private ?Collection $cartItems = null;

    public function __construct(private readonly ?Order $order = null) {}

    /**
     * The cart from the request: its lines, its discount (an amount, or a percent when one is given), the item search,
     * and the power and packet type each item card is on.
     *
     * @param  array<string, mixed>  $input
     */
    public static function from(array $input, ?Order $order = null): self
    {
        $cart = new self($order);
        $cart->itemSearch = trim((string) ($input['search'] ?? ''));
        $cart->cartDiscount = max(0.0, (float) ($input['discount'] ?? 0));
        $cart->cartDiscountPercent = isset($input['discount_percent']) && $input['discount_percent'] !== '' && $input['discount_percent'] !== null
            ? min(100.0, max(0.0, (float) $input['discount_percent']))
            : null;
        $cart->chosenPowers = array_map(fn (mixed $power): int|string => $power === 'none' || $power === null ? 'none' : (int) $power, (array) ($input['chosen_powers'] ?? []));
        $cart->chosenTypes = array_map('strval', (array) ($input['chosen_types'] ?? []));

        foreach ((array) ($input['cart'] ?? $input['lines'] ?? []) as $line) {
            if (! is_array($line) || ! isset($line['item_id']) || (int) ($line['packets'] ?? 0) <= 0) {
                continue;
            }

            $lensPowerId = filled($line['lens_power_id'] ?? null) ? (int) $line['lens_power_id'] : null;
            $colors = Item::colorMix(is_array($line['colors'] ?? null) ? $line['colors'] : []);
            $key = (int) $line['item_id'].'#'.Item::packetTypeKey($lensPowerId, $colors);

            $cart->cart[$key] = [
                'item_id' => (int) $line['item_id'],
                'lens_power_id' => $lensPowerId,
                'colors' => $colors,
                'packets' => ($cart->cart[$key]['packets'] ?? 0) + (int) $line['packets'],
            ];
        }

        return $cart;
    }

    /**
     * The order's saved lines, keyed like the cart, each with its pieces, packets, and the price it was sold at.
     *
     * @return array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: int, packets: int, price: float}>
     */
    public function savedLines(): array
    {
        return $this->linesOfThisOrder();
    }

    /**
     * Whether the cart has no lines.
     */
    public function isEmpty(): bool
    {
        return $this->cart === [];
    }

    /**
     * The cart's lines as the checkout takes them.
     *
     * @return list<CheckoutLine>
     */
    public function lines(): array
    {
        return $this->checkoutLines();
    }

    /**
     * What the discount takes off the cart's total: the percent of the total to a whole dinar, or the amount.
     */
    public function discount(): float
    {
        return $this->cartSummary()['discount'];
    }

    /**
     * The cart summary, each line with the packets left of its type, so the browser keeps the packets within the stock.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $summary = $this->cartSummary();

        $summary['lines'] = array_map(function (array $line): array {
            $cartLine = $this->cart[$line['key']];
            $left = $this->packetsLeftOf($cartLine);

            return [
                ...$line,
                'item_id' => $cartLine['item_id'],
                'lens_power_id' => $cartLine['lens_power_id'],
                'colorIds' => $cartLine['colors'],
                'packetsLeft' => $left['packets'],
                'dozensLeft' => $this->number($left['dozens']),
            ];
        }, $summary['lines']);

        return $summary;
    }

    /**
     * An item as its card shows it (the panel's card), with the cart line its + adds: the item, the power, and the colors of one
     * packet of the type the card is on. Null when nothing is left to add.
     *
     * @return array<string, mixed>
     */
    protected function itemCard(Item $item): array
    {
        $powerId = $this->powerOf($item, $this->powersOf($item));
        $type = $this->typeOf($item, $powerId);

        return [
            ...$this->cardOfTheItem($item),
            'line' => $type === null ? null : [
                'item_id' => $item->id,
                'lens_power_id' => $type['lens_power_id'],
                'colors' => array_map(fn (array $color): array => ['color_id' => (int) $color['id'], 'pieces' => (float) $color['pieces']], $type['colors']),
            ],
            'powerKey' => $this->powerKey($powerId),
        ];
    }

    /**
     * What is left of a cart line's packet type, from the cart's items read once with their stock, instead of once per line.
     *
     * @param  array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, packets: int}  $line
     * @return array{packets: int, dozens: float}
     */
    protected function packetsLeftOf(array $line): array
    {
        $this->cartItems ??= Item::query()->whereIn('id', array_column($this->cart, 'item_id'))->with(Item::stockRelations())->get()->keyBy('id');
        $item = $this->cartItems->get($line['item_id']);
        $type = $item === null ? null : collect($this->typesAt($item, $line['lens_power_id']))
            ->firstWhere('key', Item::packetTypeKey($line['lens_power_id'], $line['colors']));

        return ['packets' => (int) ($type['available_packets'] ?? 0), 'dozens' => (float) ($type['available_quantity'] ?? 0)];
    }

    /**
     * The order's saved lines, keyed like the cart, as EditOrder reads them. A new sale has none.
     *
     * @return array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: int, packets: int, price: float}>
     */
    protected function linesOfThisOrder(): array
    {
        if ($this->order === null) {
            return [];
        }

        return $this->savedLines ??= $this->order->items()->get()
            ->mapWithKeys(fn (OrderItem $line): array => [
                $line->item_id.'#'.Item::packetTypeKey($line->lens_power_id, $line->colors) => [
                    'item_id' => (int) $line->item_id,
                    'lens_power_id' => $line->lens_power_id === null ? null : (int) $line->lens_power_id,
                    'colors' => Item::colorMix($line->colors),
                    'pieces' => (int) $line->pieces,
                    'packets' => (int) round($line->packets()),
                    'price' => (float) $line->price,
                ],
            ])
            ->all();
    }
}
