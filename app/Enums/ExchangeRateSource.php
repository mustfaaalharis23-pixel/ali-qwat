<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a purchase invoice's exchange rate came from: one of the two banks it is fetched from, or typed by hand.
 */
enum ExchangeRateSource: string implements HasLabel
{
    case AgriculturalBankOfChina = 'agricultural_bank_of_china';
    case ChinaMerchantsBank = 'china_merchants_bank';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return __("filament/resources/invoice.exchange_rate_sources.{$this->value}");
    }

    /**
     * The bank's public page of its exchange rates, so the user can open it and see the rate there; null for a rate typed by hand.
     */
    public function url(): ?string
    {
        return match ($this) {
            self::AgriculturalBankOfChina => 'https://ewealth.abchina.com/ForeignExchange/ListPrice/',
            self::ChinaMerchantsBank => 'https://fx.cmbchina.com/hq/',
            self::Manual => null,
        };
    }
}
