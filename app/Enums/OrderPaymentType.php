<?php

namespace App\Enums;

use App\Enums\Contracts\HasColor;
use App\Enums\Contracts\HasLabel;

enum OrderPaymentType: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Postpaid = 'postpaid';

    public function getLabel(): string
    {
        return __("orders.payment_types.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Postpaid => 'warning',
        };
    }
}
