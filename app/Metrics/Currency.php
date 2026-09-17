<?php

namespace App\Metrics;

/**
 * The currencies a metric card can show its amount in. It only names the currency; amounts are not converted.
 */
enum Currency: string
{
    case IraqiDinar = 'IQD';
    case UsDollar = 'USD';
    case ChineseYuan = 'CNY';

    /**
     * The currency symbol in the current language, such as د.ع in Arabic and IQD in English.
     */
    public function symbol(): string
    {
        return __("metrics.currencies.{$this->value}");
    }
}
