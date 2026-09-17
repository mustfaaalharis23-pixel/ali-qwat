<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The two sides that confirm a purchase invoice: the importer confirms it first, and the receiver confirms it after.
 * The quantity enters the stock only after both of them confirm.
 */
enum InvoiceConfirmation: string implements HasLabel
{
    case Importer = 'importer';
    case Receiver = 'receiver';

    public function getLabel(): string
    {
        return __("filament/resources/invoice.confirmations.{$this->value}");
    }

    /**
     * The policy ability this side's confirmation needs.
     */
    public function ability(): string
    {
        return match ($this) {
            self::Importer => 'confirmAsImporter',
            self::Receiver => 'confirmAsReceiver',
        };
    }

    /**
     * The status an invoice must have before this side confirms it.
     */
    public function requiredStatus(): InvoiceStatus
    {
        return match ($this) {
            self::Importer => InvoiceStatus::Pending,
            self::Receiver => InvoiceStatus::ImporterConfirmed,
        };
    }

    /**
     * The status the invoice gets when this side confirms it.
     */
    public function nextStatus(): InvoiceStatus
    {
        return match ($this) {
            self::Importer => InvoiceStatus::ImporterConfirmed,
            self::Receiver => InvoiceStatus::Confirmed,
        };
    }

    /**
     * The column that holds the user who confirmed for this side.
     */
    public function checkerColumn(): string
    {
        return "{$this->value}_checker_id";
    }

    /**
     * The column that holds when this side confirmed.
     */
    public function confirmedAtColumn(): string
    {
        return "{$this->value}_confirmed_at";
    }
}
