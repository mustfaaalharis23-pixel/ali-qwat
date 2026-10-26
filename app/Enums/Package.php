<?php

namespace App\Enums;

use App\Models\Item;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Number;

/**
 * What a line is sold in: an item is sold by the whole packet only, and a packet holds the item's pieces per packet
 * (20 or 24), so its size comes from the item. The line's pieces are its packets × that size, and its price is per dozen.
 */
enum Package: string implements HasLabel
{
    case Packet = 'packet';

    public function getLabel(): string
    {
        return __("orders.packages.{$this->value}");
    }

    /**
     * How many pieces one of this package holds for this item: the item's pieces per packet,
     * or null when the item has no packet size, so it is not sold.
     */
    public function sizeFor(?Item $item): ?int
    {
        return $item?->pieces_per_packet === null ? null : (int) $item->pieces_per_packet;
    }

    /**
     * These pieces as packages of the given size, to be read on the invoice: 140 pieces with a packet of 20 is "7 packets".
     */
    public function describe(float $pieces, int $size): string
    {
        $count = round($pieces / max($size, 1), 2);

        return trans_choice("orders.package_counts.{$this->value}", $count, [
            'count' => Number::format($count, maxPrecision: 2, locale: app()->getLocale()),
        ]);
    }
}
