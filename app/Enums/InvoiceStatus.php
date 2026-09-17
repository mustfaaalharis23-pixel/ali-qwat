<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case ImporterConfirmed = 'importer_confirmed';
    case Confirmed = 'confirmed';

    public function getLabel(): string
    {
        return __("filament/resources/invoice.statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::ImporterConfirmed => 'info',
            self::Confirmed => 'success',
        };
    }
}
