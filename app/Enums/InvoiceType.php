<?php

namespace App\Enums;

use App\Enums\Contracts\HasColor;
use App\Enums\Contracts\HasLabel;

enum InvoiceType: string implements HasColor, HasLabel
{
    case Input = 'input';
    case Output = 'output';

    public function getLabel(): string
    {
        return __("filament/resources/general_description.types.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Input => 'success',
            self::Output => 'danger',
        };
    }
}
