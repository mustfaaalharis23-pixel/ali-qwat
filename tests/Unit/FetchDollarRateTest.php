<?php

namespace Tests\Unit;

use App\Invoices\FetchDollarRate;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FetchDollarRateTest extends TestCase
{
    private const string URL = 'https://ewealth.abchina.com/app/data/api/DataService/ExchangeRateV2';

    /**
     * EN: The rate is read from the Agricultural Bank of China: the US dollar's benchmark price, which is for 100 dollars,
     *     divided by 100, so it is the yuan in one dollar.
     * AR: السعر ينقرأ من البنك الزراعي الصيني: السعر المرجعي للدولار، وهو لكل 100 دولار،
     *     مقسوم على 100، فيطلع كم يوان بالدولار الواحد.
     */
    public function test_rate_is_the_dollar_benchmark_price_over_100(): void
    {
        Process::fake(['*' => Process::result(json_encode($this->body([
            $this->row('14', '美元(USD)', '671.22500000'),
            $this->row('38', '欧元(EUR)', '762.78000000'),
        ])))]);

        $this->assertEqualsWithDelta(6.71225, (new FetchDollarRate)->handle(), 0.000001);
    }

    /**
     * EN: The bank's server only speaks an old TLS that is refused by default, so the old TLS is allowed for this one request only:
     *     it runs in its own process, which is given an OpenSSL config that allows it, and nothing else in the app is changed.
     * AR: سيرفر البنك يستخدم تشفير قديم مرفوض افتراضياً، فالتشفير القديم ينسمح لهذا الطلب بس:
     *     الطلب يشتغل بعملية منفصلة تاخذ إعداد OpenSSL يسمح بيه، وما يتغير أي شي ثاني بالتطبيق.
     */
    public function test_old_tls_is_allowed_only_for_the_bank_request(): void
    {
        Process::fake(['*' => Process::result(json_encode($this->body([$this->row('14', '美元(USD)', '671.225')])))]);

        (new FetchDollarRate)->handle();

        $config = null;

        Process::assertRan(function (PendingProcess $process) use (&$config): bool {
            $config = $process->environment['OPENSSL_CONF'] ?? null;

            return in_array(self::URL, (array) $process->command, true)
                && is_string($config)
                && is_file($config)
                && str_contains((string) file_get_contents($config), 'UnsafeLegacyRenegotiation');
        });
        // The app's own process keeps its default OpenSSL config.
        $this->assertNotSame($config, getenv('OPENSSL_CONF'));
    }

    /**
     * EN: When the bank cannot be reached (no connection or it takes too long), there is no rate, and no error is thrown.
     * AR: إذا البنك ما ينوصله (ماكو اتصال أو طوّل)، ماكو سعر، وما يطلع خطأ.
     */
    public function test_no_rate_when_the_bank_cannot_be_reached(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'Operation timed out', exitCode: 28)]);

        $this->assertNull((new FetchDollarRate)->handle());
    }

    /**
     * EN: When the bank answers with something that does not give a usable dollar price, there is no rate, and no error is thrown.
     * AR: إذا البنك رد بشي ما بيه سعر دولار صالح، ماكو سعر، وما يطلع خطأ.
     */
    #[DataProvider('unusableAnswers')]
    public function test_no_rate_when_the_answer_has_no_usable_dollar_price(int $exitCode, string $output): void
    {
        Process::fake(['*' => Process::result(output: $output, exitCode: $exitCode)]);

        $this->assertNull((new FetchDollarRate)->handle());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function unusableAnswers(): array
    {
        $row = fn (string $price): array => ['CurrId' => '14', 'CurrName' => '美元(USD)', 'BenchMarkPrice' => $price];
        $body = fn (array $rows, string $errorCode = '0'): string => (string) json_encode(['ErrorCode' => $errorCode, 'ErrorMsg' => '', 'Data' => ['Table' => $rows]]);

        return [
            // EN: The bank's server fails (curl exits with 22 on an HTTP error). AR: سيرفر البنك يفشل (curl يطلع بـ 22 على خطأ HTTP).
            'server error' => [22, ''],
            // EN: The answer is not JSON. AR: الرد موب JSON.
            'not json' => [0, '<html>maintenance</html>'],
            // EN: The answer is empty. AR: الرد فارغ.
            'empty answer' => [0, ''],
            // EN: The bank reports an error. AR: البنك يرجع خطأ.
            'bank error code' => [0, $body([$row('671.225')], '1')],
            // EN: The dollar is not in the list. AR: الدولار موب موجود بالقائمة.
            'no dollar row' => [0, $body([['CurrId' => '38', 'CurrName' => '欧元(EUR)', 'BenchMarkPrice' => '762.78']])],
            // EN: The dollar's price is zero. AR: سعر الدولار صفر.
            'zero price' => [0, $body([$row('0.00000000')])],
            // EN: The dollar's price is not a number. AR: سعر الدولار موب رقم.
            'price not a number' => [0, $body([$row('N/A')])],
        ];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function body(array $rows): array
    {
        return ['ErrorCode' => '0', 'ErrorMsg' => '', 'Data' => ['ErrorCode' => '', 'Table' => $rows]];
    }

    /**
     * @return array<string, string>
     */
    private function row(string $currencyId, string $name, string $benchmarkPrice): array
    {
        return [
            'CurrId' => $currencyId,
            'CurrName' => $name,
            'BenchMarkPrice' => $benchmarkPrice,
            'BuyingPrice' => $benchmarkPrice,
            'SellPrice' => $benchmarkPrice,
            'PublishTime' => '2026-09-24T20:35:25+08:00',
        ];
    }
}
