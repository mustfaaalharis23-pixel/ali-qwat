<?php

namespace App\Orders\Checkout;

/**
 * One sold line of a checkout: the item and its quantity.
 * There is no price here on purpose: the price is always taken from the item's sale price in the database.
 */
final readonly class CheckoutLine
{
    public function __construct(
        public int $itemId,
        public float $quantity,
    ) {}
}
