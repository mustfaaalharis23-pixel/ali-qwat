<?php

namespace Tests\Unit;

use App\Enums\ExchangeRateSource;
use App\Invoices\FetchDollarRate;
use App\Invoices\FetchExchangeRate;
use App\Invoices\FetchMerchantsBankDollarRate;
use Mockery\MockInterface;
use Tests\TestCase;

class FetchExchangeRateTest extends TestCase
{
    /**
     * EN: The Agricultural Bank of China is asked first; when it gives a rate, that rate is taken with it as the source,
     *     and China Merchants Bank is not asked.
     * AR: البنك الزراعي الصيني ينسأل أول؛ إذا أعطى سعر، ينأخذ سعره والمصدر هو،
     *     وبنك China Merchants ما ينسأل.
     */
    public function test_agricultural_bank_rate_is_taken_first(): void
    {
        $this->mock(FetchDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn(6.7121));
        $this->mock(FetchMerchantsBankDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->never());

        $rate = (new FetchExchangeRate)->handle();

        $this->assertSame(['rate' => 6.7121, 'source' => ExchangeRateSource::AgriculturalBankOfChina], $rate);
    }

    /**
     * EN: When the Agricultural Bank of China gives no rate, China Merchants Bank's rate is taken, with it as the source.
     * AR: إذا البنك الزراعي الصيني ما أعطى سعر، ينأخذ سعر بنك China Merchants، والمصدر هو.
     */
    public function test_merchants_bank_rate_is_taken_when_the_agricultural_bank_gives_none(): void
    {
        $this->bankRates(agricultural: null, merchants: 6.7147);

        $rate = (new FetchExchangeRate)->handle();

        $this->assertSame(['rate' => 6.7147, 'source' => ExchangeRateSource::ChinaMerchantsBank], $rate);
    }

    /**
     * EN: When neither bank gives a rate, there is no rate, so the user types it by hand.
     * AR: إذا ولا بنك أعطى سعر، ماكو سعر، فالمستخدم يكتبه بيده.
     */
    public function test_no_rate_when_neither_bank_gives_one(): void
    {
        $this->bankRates(agricultural: null, merchants: null);

        $this->assertNull((new FetchExchangeRate)->handle());
    }

    /**
     * Each bank answers with the given rate, or with none.
     */
    private function bankRates(?float $agricultural, ?float $merchants): void
    {
        $this->mock(FetchDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn($agricultural));
        $this->mock(FetchMerchantsBankDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn($merchants));
    }
}
