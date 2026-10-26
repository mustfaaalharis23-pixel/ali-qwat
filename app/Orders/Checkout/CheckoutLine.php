<?php

namespace App\Orders\Checkout;

use App\Models\Item;

/**
 * One sold line of a checkout: the item, the whole packets sold of it (or, for an item without a packet size, its dozens),
 * the lens power it was sold at (null for an item sold without a power), and the colors in one of its packets
 * (empty for an item sold without colors). There is no price here on purpose: the price is always taken from the item's
 * sale price of a dozen in the database.
 */
final readonly class CheckoutLine
{
    /**
     * @param  list<array{color_id: int, pieces: float}>  $colors
     */
    public function __construct(
        public int $itemId,
        public float $quantity = 0,
        public ?int $lensPowerId = null,
        public ?int $packets = null,
        public array $colors = [],
    ) {}

    /**
     * The dozens this line sells of the item: its packets × the item's pieces per packet ÷ the pieces in a dozen,
     * or its given dozens for a line without packets.
     */
    public function dozensFor(Item $item): float
    {
        if ($this->packets === null) {
            return $this->quantity;
        }

        return $this->packets * (float) $item->pieces_per_packet / Item::PIECES_IN_A_DOZEN;
    }
}
