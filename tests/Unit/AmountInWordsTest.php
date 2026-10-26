<?php

namespace Tests\Unit;

use App\Invoices\AmountInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmountInWordsTest extends TestCase
{
    /**
     * EN: Amounts in dinars with how they are written in words. Arabic is written the way a receipt writes it: units before tens,
     *     the thousand and the million in their singular, dual, and plural (3 to 10) forms, and a fraction rounded to the nearest dinar.
     *     English and Chinese use the language's own spelling.
     * AR: مبالغ بالدينار وشلون تنكتب بالحروف. العربي ينكتب مثل ما يكتبه سند القبض: الآحاد قبل العشرات،
     *     والألف والمليون بالمفرد والمثنى والجمع (من 3 لـ 10)، والكسر ينقرب لأقرب دينار.
     *     الإنكليزي والصيني بالكتابة الخاصة بكل لغة.
     *
     * @return array<string, array{string, float, string}>
     */
    public static function amounts(): array
    {
        return [
            'zero' => ['ar', 0, 'صفر'],
            'units' => ['ar', 5, 'خمسة'],
            'eleven' => ['ar', 11, 'أحد عشر'],
            'twelve' => ['ar', 12, 'اثنا عشر'],
            'units and tens' => ['ar', 25, 'خمسة وعشرون'],
            'one hundred' => ['ar', 100, 'مائة'],
            'two hundred and tens' => ['ar', 250, 'مائتان وخمسون'],
            'one thousand' => ['ar', 1000, 'ألف'],
            'two thousand' => ['ar', 2000, 'ألفان'],
            'three thousand' => ['ar', 3000, 'ثلاثة آلاف'],
            'eleven thousand' => ['ar', 11000, 'أحد عشر ألف'],
            'thousands and hundreds' => ['ar', 25500, 'خمسة وعشرون ألف وخمسمائة'],
            'two hundred thousand' => ['ar', 200000, 'مائتا ألف'],
            'three hundred thousand' => ['ar', 300000, 'ثلاثمائة ألف'],
            'one million' => ['ar', 1000000, 'مليون'],
            'two million and thousands' => ['ar', 2500000, 'مليونان وخمسمائة ألف'],
            'ten million' => ['ar', 10000000, 'عشرة ملايين'],
            'million and thousands' => ['ar', 1783000, 'مليون وسبعمائة وثلاثة وثمانون ألف'],
            'every part' => ['ar', 2083250, 'مليونان وثلاثة وثمانون ألف ومائتان وخمسون'],
            'fraction rounded' => ['ar', 1500.6, 'ألف وخمسمائة وواحد'],
            'english' => ['en', 1783000, 'one million seven hundred eighty-three thousand'],
            'chinese' => ['zh_CN', 1783000, '一百七十八万三千'],
        ];
    }

    /**
     * EN: An amount is written in words in the given language.
     * AR: المبلغ ينكتب بالحروف باللغة المطلوبة.
     */
    #[DataProvider('amounts')]
    public function test_amount_is_written_in_words(string $locale, float $amount, string $words): void
    {
        $this->assertSame($words, AmountInWords::spell($amount, $locale));
    }
}
