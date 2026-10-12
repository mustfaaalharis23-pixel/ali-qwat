<?php

namespace App\Orders\Checkout;

use App\Enums\Package;

/**
 * One sold line of a checkout: the item, its quantity in pieces, and the package it was sold in.
 * There is no price here on purpose: the price is always taken from the item's sale price in the database.
 * The package is information only, so it never changes the quantity or the price.
 */
final readonly class CheckoutLine
{
    public function __construct(
        public int $itemId,
        public float $quantity,
        public Package $package = Package::Piece,
    ) {}
}
