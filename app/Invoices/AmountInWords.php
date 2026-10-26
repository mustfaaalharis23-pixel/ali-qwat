<?php

namespace App\Invoices;

use Illuminate\Support\Number;

/**
 * An amount of dinars written in words, for the line of a receipt that says the amount in letters.
 * A dinar has no fraction, so the amount is rounded to the nearest dinar first.
 * Arabic is written here the way a receipt writes it: units before tens, and the thousand, the million, and the billion
 * in their singular, dual, and plural (3 to 10) forms. Every other language uses its own spelling.
 */
class AmountInWords
{
    /**
     * @var list<string>
     */
    private const array UNITS = [
        '', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
        'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر',
    ];

    /**
     * @var array<int, string>
     */
    private const array TENS = [2 => 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    /**
     * @var list<string>
     */
    private const array HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /**
     * Each scale from the largest, with its singular, dual, and plural.
     *
     * @var array<int, array{string, string, string}>
     */
    private const array SCALES = [
        1_000_000_000 => ['مليار', 'ملياران', 'مليارات'],
        1_000_000 => ['مليون', 'مليونان', 'ملايين'],
        1_000 => ['ألف', 'ألفان', 'آلاف'],
    ];

    /**
     * The amount in words in the given language, or the language of the app when none is given.
     */
    public static function spell(float $amount, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $dinars = (int) round($amount);

        return $locale === 'ar' ? self::arabic($dinars) : Number::spell($dinars, locale: $locale);
    }

    /**
     * A whole number in Arabic words: each scale it holds, from the largest, then what is left under a thousand, joined by "و".
     */
    private static function arabic(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $parts = [];

        foreach (self::SCALES as $scale => $names) {
            if ($number >= $scale) {
                $parts[] = self::counted(intdiv($number, $scale), $names);
                $number %= $scale;
            }
        }

        if ($number > 0) {
            $parts[] = self::belowThousand($number);
        }

        return implode(' و', $parts);
    }

    /**
     * A count of a scale: the singular alone for one, the dual alone for two, the plural after 3 to 10, and the singular after any other count.
     * Two hundred drops its last letter before the scale, as in "مائتا ألف".
     *
     * @param  array{string, string, string}  $names
     */
    private static function counted(int $count, array $names): string
    {
        [$singular, $dual, $plural] = $names;

        return match (true) {
            $count === 1 => $singular,
            $count === 2 => $dual,
            $count === 200 => "مائتا {$singular}",
            $count % 100 >= 3 && $count % 100 <= 10 => self::arabic($count)." {$plural}",
            default => self::arabic($count)." {$singular}",
        };
    }

    /**
     * A number from 1 to 999: the hundreds, then the units before the tens.
     */
    private static function belowThousand(int $number): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;
        $units = $rest % 10;

        $restInWords = match (true) {
            $rest === 0 => '',
            $rest < 20 => self::UNITS[$rest],
            $units === 0 => self::TENS[intdiv($rest, 10)],
            default => self::UNITS[$units].' و'.self::TENS[intdiv($rest, 10)],
        };

        return implode(' و', array_filter([self::HUNDREDS[$hundreds], $restInWords]));
    }
}
