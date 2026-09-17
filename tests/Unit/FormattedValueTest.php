<?php

namespace Tests\Unit;

use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FormattedValueTest extends TestCase
{
    /**
     * EN: The currencies a card can show: Iraqi dinar, US dollar, and Chinese yuan.
     * AR: العملات اللي يكدر الكارد يعرضها: الدينار العراقي، الدولار الأمريكي، واليوان الصيني.
     */
    public function test_currencies_are_the_agreed_list(): void
    {
        $this->assertSame(['IQD', 'USD', 'CNY'], array_column(Currency::cases(), 'value'));
    }

    /**
     * EN: A plain number has thousands separators and up to two decimals, without extra zeros, and no currency.
     * AR: الرقم العادي بيه فواصل الآلاف ولحد رقمين بعد الفاصلة بدون أصفار زايدة، وبدون عملة.
     */
    public function test_plain_number_has_up_to_two_decimals_and_no_currency(): void
    {
        $this->assertSame('1,500', FormattedValue::of(1500)->number);
        $this->assertSame('1,500.46', FormattedValue::of(1500.456)->number);
        $this->assertNull(FormattedValue::of(1500)->currency);
    }

    /**
     * EN: An amount in Iraqi dinar has no decimals; its fraction is rounded to the nearest dinar.
     * AR: المبلغ بالدينار العراقي ما بيه أرقام بعد الفاصلة؛ الكسر ينقرب لأقرب دينار.
     */
    public function test_iraqi_dinar_has_no_decimals(): void
    {
        $this->assertSame('150,000,000', FormattedValue::of(150000000, Currency::IraqiDinar)->number);
        $this->assertSame('1,501', FormattedValue::of(1500.5, Currency::IraqiDinar)->number);
    }

    /**
     * EN: An amount in dollar or yuan has two decimals, unless it is whole, then it has none.
     * AR: المبلغ بالدولار أو اليوان بيه رقمين بعد الفاصلة، إلا إذا كان عدد صحيح فما بيه فاصلة.
     */
    public function test_dollar_and_yuan_have_two_decimals_only_when_not_whole(): void
    {
        $this->assertSame('1,500.50', FormattedValue::of(1500.5, Currency::UsDollar)->number);
        $this->assertSame('1,500.46', FormattedValue::of(1500.456, Currency::ChineseYuan)->number);
        $this->assertSame('1,500', FormattedValue::of(1500, Currency::UsDollar)->number);
        $this->assertSame('1,500', FormattedValue::of(1500.001, Currency::ChineseYuan)->number);
    }

    /**
     * EN: A negative amount of money keeps its minus sign.
     * AR: المبلغ المالي السالب يبقى بإشارة السالب.
     */
    public function test_negative_money_keeps_its_minus_sign(): void
    {
        $this->assertSame('-2,501', FormattedValue::of(-2500.75, Currency::IraqiDinar)->number);
        $this->assertSame('-2,500.75', FormattedValue::of(-2500.75, Currency::UsDollar)->number);
    }

    /**
     * EN: In Arabic, the number keeps the same digits and separators as in English.
     * AR: بالعربي، الرقم يبقى بنفس الأرقام والفواصل مثل الإنكليزي.
     */
    public function test_number_in_arabic_keeps_the_same_digits_and_separators(): void
    {
        App::setLocale('ar');

        $this->assertSame('150,000,000', FormattedValue::of(150000000, Currency::IraqiDinar)->number);
    }

    /**
     * EN: The currency symbols in each language, in the order of the currencies.
     * AR: رموز العملات بكل لغة، بنفس ترتيب العملات.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function symbols(): array
    {
        return [
            'Arabic' => ['ar', ['د.ع', '$', '¥']],
            'English' => ['en', ['IQD', '$', '¥']],
        ];
    }

    /**
     * EN: An amount of money shows its currency symbol in the current language.
     * AR: المبلغ المالي يعرض رمز عملته باللغة الحالية.
     *
     * @param  list<string>  $expected
     */
    #[DataProvider('symbols')]
    public function test_money_shows_its_currency_symbol_in_the_current_language(string $locale, array $expected): void
    {
        App::setLocale($locale);

        $this->assertSame(
            $expected,
            array_map(fn (Currency $currency): ?string => FormattedValue::of(100, $currency)->currency, Currency::cases()),
        );
    }
}
