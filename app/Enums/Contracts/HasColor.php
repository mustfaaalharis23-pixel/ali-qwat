<?php

namespace App\Enums\Contracts;

/**
 * An enum whose cases have a color, such as success or danger, for their badge.
 */
interface HasColor
{
    public function getColor(): string;
}
