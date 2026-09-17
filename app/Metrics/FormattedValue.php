<?php

namespace App\Metrics;

use Illuminate\Support\Number;

/**
 * A metric value ready to be shown: the formatted number, and the currency symbol when it is an amount of money.
 */
readonly class FormattedValue
{
    public function __construct(
        public string $number,
        public ?string $currency,
    ) {}

    /**
     * Without a currency, a plain number with up to two decimals.
     * With a currency, an amount of money with the currency symbol: Iraqi dinar is rounded to a whole number,
     * and any other currency has two decimals unless it is whole after rounding, then it has none.
     */
    public static function of(float $value, ?Currency $currency = null): self
    {
        $locale = app()->getLocale();

        if ($currency === null) {
            return new self(Number::format($value, maxPrecision: 2, locale: $locale), null);
        }

        $decimals = $currency === Currency::IraqiDinar ? 0 : 2;
        $rounded = round($value, $decimals);
        $precision = $rounded === floor($rounded) ? 0 : $decimals;

        return new self(Number::format($rounded, precision: $precision, locale: $locale), $currency->symbol());
    }
}
