<?php

namespace App\Invoices;

use Illuminate\Support\Facades\Process;
use Throwable;

class FetchDollarRate
{
    private const string URL = 'https://ewealth.abchina.com/app/data/api/DataService/ExchangeRateV2';

    /**
     * The US dollar's currency id in the Agricultural Bank of China's price list.
     */
    private const string DOLLAR_ID = '14';

    /**
     * The yuan in one US dollar, read from the Agricultural Bank of China: the dollar's benchmark price, which is for
     * 100 dollars, divided by 100. Null when the bank cannot be reached or its answer has no usable dollar price,
     * so the user types the rate by hand.
     *
     * The bank's server only speaks an old TLS that OpenSSL refuses by default, so the request runs through curl in its own
     * process, given an OpenSSL config that allows the old TLS. The app's own process and its other connections are not changed.
     */
    public function handle(): ?float
    {
        try {
            $result = Process::env(['OPENSSL_CONF' => resource_path('openssl/legacy-renegotiation.cnf')])
                ->timeout(10)
                ->run(['curl', '--silent', '--fail', '--max-time', '5', '--user-agent', 'Mozilla/5.0', self::URL]);
        } catch (Throwable) {
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        $answer = json_decode($result->output(), true);

        if (! is_array($answer) || ($answer['ErrorCode'] ?? null) !== '0') {
            return null;
        }

        $dollar = collect(data_get($answer, 'Data.Table') ?? [])
            ->first(fn (mixed $row): bool => is_array($row) && ($row['CurrId'] ?? null) === self::DOLLAR_ID);
        $price = $dollar['BenchMarkPrice'] ?? null;

        if (! is_numeric($price) || (float) $price <= 0) {
            return null;
        }

        return (float) $price / 100;
    }
}
