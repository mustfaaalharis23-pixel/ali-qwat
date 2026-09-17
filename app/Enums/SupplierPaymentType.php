<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SupplierPaymentType: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Postpaid = 'postpaid';

    public function getLabel(): string
    {
        return __("filament/resources/supplier.payment_types.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Postpaid => 'warning',
        };
    }
}
