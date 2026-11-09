<?php

namespace App\Enums\Contracts;

/**
 * An enum whose cases have a name in the current language, shown in place of their stored value.
 */
interface HasLabel
{
    public function getLabel(): string;
}
