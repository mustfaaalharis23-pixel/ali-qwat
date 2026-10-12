<?php

namespace App\Invoices;

use Illuminate\Support\Facades\Http;
use Throwable;

class FetchMerchantsBankDollarRate
{
    private const string URL = 'https://fx.cmbchina.com/api/v1/fx/rate';

    /**
     * The yuan in one US dollar, read from China Merchants Bank: the middle of the dollar's buying and selling prices,
     * which are for 100 dollars, divided by 100. Null when the bank cannot be reached or its answer has no usable dollar prices.
     */
    public function handle(): ?float
    {
        try {
            $response = Http::timeout(5)->withUserAgent('Mozilla/5.0')->get(self::URL);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->json('returnCode') !== 'SUC0000') {
            return null;
        }

        $dollar = collect($response->json('body') ?? [])
            ->first(fn (mixed $row): bool => is_array($row) && str_contains((string) ($row['ccyNbrEng'] ?? ''), 'USD'));
        $buying = $dollar['rthBid'] ?? null;
        $selling = $dollar['rthOfr'] ?? null;

        if (! is_numeric($buying) || ! is_numeric($selling) || (float) $buying <= 0 || (float) $selling <= 0) {
            return null;
        }

        return ((float) $buying + (float) $selling) / 2 / 100;
    }
}
