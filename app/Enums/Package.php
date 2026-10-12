<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Number;

/**
 * The packages a line can be sold in, one list for all the items. A package is information only:
 * the line's quantity is always in pieces, and the price and the stock are worked out from the pieces alone.
 */
enum Package: string implements HasLabel
{
    case Piece = 'piece';
    case Dozen = 'dozen';
    case Packet = 'packet';

    public function getLabel(): string
    {
        return __("orders.packages.{$this->value}");
    }

    /**
     * How many pieces one of this package holds.
     */
    public function size(): int
    {
        return match ($this) {
            self::Piece => 1,
            self::Dozen => 12,
            self::Packet => 20,
        };
    }

    /**
     * This quantity of pieces as packages of this one, to be read on the invoice: 24 pieces with a dozen is "2 dozen",
     * and 300 with a piece is "300 pieces".
     */
    public function describe(float $quantity): string
    {
        $count = round($quantity / $this->size(), 2);

        return trans_choice("orders.package_counts.{$this->value}", $count, [
            'count' => Number::format($count, maxPrecision: 2, locale: app()->getLocale()),
        ]);
    }

    /**
     * Whether this quantity of pieces makes whole packages of this one: 24 with a dozen does, 25 does not.
     * A piece takes any quantity, exactly as a line did before there were packages.
     */
    public function holdsWhole(float $quantity): bool
    {
        if ($this === self::Piece) {
            return true;
        }

        $remainder = fmod($quantity, $this->size());

        return $remainder < 0.0001 || $this->size() - $remainder < 0.0001;
    }
}
