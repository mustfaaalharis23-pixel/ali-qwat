<?php

namespace Tests\Unit;

use App\Invoices\FetchMerchantsBankDollarRate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FetchMerchantsBankDollarRateTest extends TestCase
{
    private const string URL = 'https://fx.cmbchina.com/api/v1/fx/rate';

    /**
     * EN: The rate is read from China Merchants Bank: the middle of the dollar's buying and selling prices, which are for 100 dollars,
     *     divided by 100, so it is the yuan in one dollar.
     * AR: السعر ينقرأ من بنك China Merchants: النص بين سعر شراء الدولار وسعر بيعه، وهم لكل 100 دولار،
     *     مقسوم على 100، فيطلع كم يوان بالدولار الواحد.
     */
    public function test_rate_is_the_middle_of_the_dollar_buying_and_selling_prices_over_100(): void
    {
        Http::fake([self::URL => Http::response($this->body([
            $this->row('港币 HKD', '85.420', '85.760'),
            $this->row('美元 USD', '670.010', '672.930'),
        ]))]);

        $this->assertEqualsWithDelta(6.7147, (new FetchMerchantsBankDollarRate)->handle(), 0.000001);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL);
    }

    /**
     * EN: When the bank cannot be reached (no connection or it takes too long), there is no rate, and no error is thrown.
     * AR: إذا البنك ما ينوصله (ماكو اتصال أو طوّل)، ماكو سعر، وما يطلع خطأ.
     */
    public function test_no_rate_when_the_bank_cannot_be_reached(): void
    {
        Http::fake([self::URL => fn () => throw new ConnectionException('timed out')]);

        $this->assertNull((new FetchMerchantsBankDollarRate)->handle());
    }

    /**
     * EN: When the bank answers with something that does not give usable dollar prices, there is no rate, and no error is thrown.
     * AR: إذا البنك رد بشي ما بيه أسعار دولار صالحة، ماكو سعر، وما يطلع خطأ.
     *
     * @param  array<string, mixed>|string  $body
     */
    #[DataProvider('unusableAnswers')]
    public function test_no_rate_when_the_answer_has_no_usable_dollar_prices(int $status, array|string $body): void
    {
        Http::fake([self::URL => Http::response($body, $status)]);

        $this->assertNull((new FetchMerchantsBankDollarRate)->handle());
    }

    /**
     * @return array<string, array{int, array<string, mixed>|string}>
     */
    public static function unusableAnswers(): array
    {
        $row = fn (string $buying, string $selling): array => ['ccyNbrEng' => '美元 USD', 'rthBid' => $buying, 'rthOfr' => $selling];
        $body = fn (array $rows, string $returnCode = 'SUC0000'): array => ['returnCode' => $returnCode, 'errorMsg' => null, 'body' => $rows];

        return [
            // EN: The bank's server fails. AR: سيرفر البنك يفشل.
            'server error' => [500, $body([$row('670.01', '672.93')])],
            // EN: The answer is not JSON. AR: الرد موب JSON.
            'not json' => [200, '<html>maintenance</html>'],
            // EN: The bank reports an error. AR: البنك يرجع خطأ.
            'bank error code' => [200, $body([$row('670.01', '672.93')], 'ERR0001')],
            // EN: The dollar is not in the list. AR: الدولار موب موجود بالقائمة.
            'no dollar row' => [200, $body([['ccyNbrEng' => '港币 HKD', 'rthBid' => '85.42', 'rthOfr' => '85.76']])],
            // EN: A dollar price is zero. AR: واحد من أسعار الدولار صفر.
            'zero price' => [200, $body([$row('0', '672.93')])],
            // EN: A dollar price is not a number. AR: واحد من أسعار الدولار موب رقم.
            'price not a number' => [200, $body([$row('670.01', 'N/A')])],
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function body(array $rows): array
    {
        return ['returnCode' => 'SUC0000', 'errorMsg' => null, 'body' => $rows];
    }

    /**
     * @return array<string, string>
     */
    private function row(string $name, string $buying, string $selling): array
    {
        return [
            'ccyNbrEng' => $name,
            'rthBid' => $buying,
            'rthOfr' => $selling,
            'rtcBid' => $buying,
            'rtcOfr' => $selling,
            'ratDat' => '2026年09月24日',
            'ratTim' => '21:12:31',
        ];
    }
}
