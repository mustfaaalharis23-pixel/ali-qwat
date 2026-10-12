<?php

namespace App\Invoices;

use App\Enums\ExchangeRateSource;

class FetchExchangeRate
{
    /**
     * The yuan in one US dollar and the bank it came from: the Agricultural Bank of China first, and China Merchants Bank
     * only when the first gives no rate. Null when neither does, so the user types the rate by hand.
     *
     * @return array{rate: float, source: ExchangeRateSource}|null
     */
    public function handle(): ?array
    {
        $rate = app(FetchDollarRate::class)->handle();

        if ($rate !== null) {
            return ['rate' => $rate, 'source' => ExchangeRateSource::AgriculturalBankOfChina];
        }

        $rate = app(FetchMerchantsBankDollarRate::class)->handle();

        if ($rate !== null) {
            return ['rate' => $rate, 'source' => ExchangeRateSource::ChinaMerchantsBank];
        }

        return null;
    }
}
