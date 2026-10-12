<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a purchase invoice's goods are, from the factory to us. The cases are in their order, and a stage only moves forward, one step at a time,
 * except when a carton label is scanned: that moves the invoice straight to Received.
 * It is information only: it does not change the invoice's confirmation status or its container.
 */
enum InvoiceStage: string implements HasColor, HasLabel
{
    case InProduction = 'in_production';
    case Manufactured = 'manufactured';
    case ReceivedAtWarehouse = 'received_at_warehouse';
    case OnTheWay = 'on_the_way';
    case Received = 'received';

    /**
     * The stage after this one, or null when this is the last stage.
     */
    public function next(): ?self
    {
        $cases = self::cases();

        return $cases[array_search($this, $cases, true) + 1] ?? null;
    }

    public function getLabel(): string
    {
        return __("filament/resources/invoice.stages.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProduction => 'gray',
            self::Manufactured => 'warning',
            self::ReceivedAtWarehouse => 'info',
            self::OnTheWay => 'primary',
            self::Received => 'success',
        };
    }
}
