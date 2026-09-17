<?php

namespace App\Metrics;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;

/**
 * The ranges a metric card can offer. Each range knows its label and its period at the current time.
 * Months, quarters, and years are whole: this month runs to its last day, and the quarters are of the current year.
 */
enum MetricRange: string implements HasLabel
{
    case All = 'all';
    case Today = 'today';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case FirstQuarter = 'first_quarter';
    case SecondQuarter = 'second_quarter';
    case ThirdQuarter = 'third_quarter';
    case FourthQuarter = 'fourth_quarter';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';

    /**
     * The chosen range when the card allows it, otherwise the card's default range.
     * The chosen value comes from the browser, so it is never trusted as it is.
     *
     * @param  list<MetricRange>  $allowed
     */
    public static function resolve(?string $value, array $allowed, self $default): self
    {
        $chosen = self::tryFrom((string) $value);

        return in_array($chosen, $allowed, true) ? $chosen : $default;
    }

    public function getLabel(): string
    {
        return __("metrics.ranges.{$this->value}");
    }

    /**
     * The period of this range at the current time.
     */
    public function period(): Period
    {
        $now = CarbonImmutable::now();

        return match ($this) {
            self::All => new Period(null, null),
            self::Today => new Period($now->startOfDay(), $now->endOfDay()),
            self::ThisMonth => new Period($now->startOfMonth(), $now->endOfMonth()),
            self::LastMonth => new Period($now->startOfMonth()->subMonth(), $now->startOfMonth()->subMonth()->endOfMonth()),
            self::FirstQuarter => self::quarterOfYear($now, 1),
            self::SecondQuarter => self::quarterOfYear($now, 2),
            self::ThirdQuarter => self::quarterOfYear($now, 3),
            self::FourthQuarter => self::quarterOfYear($now, 4),
            self::ThisYear => new Period($now->startOfYear(), $now->endOfYear()),
            self::LastYear => new Period($now->startOfYear()->subYear(), $now->startOfYear()->subYear()->endOfYear()),
        };
    }

    /**
     * The given quarter (1 to 4) of the year of the given time.
     */
    private static function quarterOfYear(CarbonImmutable $now, int $quarter): Period
    {
        $start = $now->startOfYear()->addMonths(($quarter - 1) * 3);

        return new Period($start, $start->endOfQuarter());
    }
}
