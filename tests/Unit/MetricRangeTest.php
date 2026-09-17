<?php

namespace Tests\Unit;

use App\Metrics\MetricRange;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MetricRangeTest extends TestCase
{
    /**
     * EN: The ranges a metric card can offer: all time, today, this and last month, the four quarters, and this and last year.
     * AR: الفترات اللي يكدر الكارد يعرضها: الكل، اليوم، الشهر الحالي والسابق، الأرباع الأربعة، والسنة الحالية والسابقة.
     */
    public function test_ranges_are_the_agreed_list(): void
    {
        $this->assertSame(
            ['all', 'today', 'this_month', 'last_month', 'first_quarter', 'second_quarter', 'third_quarter', 'fourth_quarter', 'this_year', 'last_year'],
            array_column(MetricRange::cases(), 'value'),
        );
    }

    /**
     * EN: All time has no start and no end.
     * AR: الكل ما إله بداية ولا نهاية.
     */
    public function test_all_time_has_no_start_and_no_end(): void
    {
        $this->travelTo('2026-05-20 10:30:00');

        $period = MetricRange::All->period();

        $this->assertNull($period->start);
        $this->assertNull($period->end);
    }

    /**
     * EN: The first and last moment of each range on 20 May 2026. Quarters are of the current year.
     * AR: أول وآخر لحظة لكل فترة بتاريخ 20 أيار 2026. الأرباع من السنة الحالية.
     *
     * @return array<string, array{MetricRange, string, string}>
     */
    public static function periods(): array
    {
        return [
            'today' => [MetricRange::Today, '2026-05-20 00:00:00', '2026-05-20 23:59:59'],
            'this month' => [MetricRange::ThisMonth, '2026-05-01 00:00:00', '2026-05-31 23:59:59'],
            'last month' => [MetricRange::LastMonth, '2026-04-01 00:00:00', '2026-04-30 23:59:59'],
            'first quarter' => [MetricRange::FirstQuarter, '2026-01-01 00:00:00', '2026-03-31 23:59:59'],
            'second quarter' => [MetricRange::SecondQuarter, '2026-04-01 00:00:00', '2026-06-30 23:59:59'],
            'third quarter' => [MetricRange::ThirdQuarter, '2026-07-01 00:00:00', '2026-09-30 23:59:59'],
            'fourth quarter' => [MetricRange::FourthQuarter, '2026-10-01 00:00:00', '2026-12-31 23:59:59'],
            'this year' => [MetricRange::ThisYear, '2026-01-01 00:00:00', '2026-12-31 23:59:59'],
            'last year' => [MetricRange::LastYear, '2025-01-01 00:00:00', '2025-12-31 23:59:59'],
        ];
    }

    /**
     * EN: Each range starts at the first moment of its first day and ends at the last moment of its last day.
     * AR: كل فترة تبدي من أول لحظة بأول يوم إلها، وتنتهي بآخر لحظة بآخر يوم إلها.
     */
    #[DataProvider('periods')]
    public function test_range_starts_and_ends_on_its_dates(MetricRange $range, string $start, string $end): void
    {
        $this->travelTo('2026-05-20 10:30:00');

        $period = $range->period();

        $this->assertSame($start, $period->start->toDateTimeString());
        $this->assertSame($end, $period->end->toDateTimeString());
    }

    /**
     * EN: On the last day of a long month, last month is still the whole previous month (31 March gives February, not early March).
     * AR: بآخر يوم من شهر طويل، الشهر السابق يبقى الشهر اللي قبله كامل (31 آذار يعطي شباط، مو بداية آذار).
     */
    public function test_last_month_on_the_last_day_of_a_long_month_is_the_previous_month(): void
    {
        $this->travelTo('2026-03-31 22:00:00');

        $period = MetricRange::LastMonth->period();

        $this->assertSame('2026-02-01 00:00:00', $period->start->toDateTimeString());
        $this->assertSame('2026-02-28 23:59:59', $period->end->toDateTimeString());
    }

    /**
     * EN: The range labels in each language, in the order of the ranges.
     * AR: تسميات الفترات بكل لغة، بنفس ترتيب الفترات.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function labels(): array
    {
        return [
            'Arabic' => ['ar', ['الكل', 'اليوم', 'الشهر الحالي', 'الشهر السابق', 'الربع الأول', 'الربع الثاني', 'الربع الثالث', 'الربع الرابع', 'السنة الحالية', 'السنة السابقة']],
            'English' => ['en', ['All time', 'Today', 'This month', 'Last month', 'First quarter', 'Second quarter', 'Third quarter', 'Fourth quarter', 'This year', 'Last year']],
        ];
    }

    /**
     * EN: Each range shows its label in the current language.
     * AR: كل فترة تعرض تسميتها باللغة الحالية.
     *
     * @param  list<string>  $expected
     */
    #[DataProvider('labels')]
    public function test_range_label_is_shown_in_the_current_language(string $locale, array $expected): void
    {
        App::setLocale($locale);

        $this->assertSame($expected, array_map(fn (MetricRange $range): string => $range->getLabel(), MetricRange::cases()));
    }

    /**
     * EN: The chosen range is used when the card allows it.
     * AR: الفترة المختارة تنستخدم إذا الكارد يسمح بيها.
     */
    public function test_chosen_range_is_used_when_the_card_allows_it(): void
    {
        $range = MetricRange::resolve('last_month', [MetricRange::ThisMonth, MetricRange::LastMonth], MetricRange::ThisMonth);

        $this->assertSame(MetricRange::LastMonth, $range);
    }

    /**
     * EN: Chosen values that are rejected: a range the card does not allow, an unknown value, and no value.
     * AR: قيم مختارة تنرفض: فترة الكارد ما يسمح بيها، قيمة مو معروفة، وبدون قيمة.
     *
     * @return array<string, array{?string}>
     */
    public static function rejectedValues(): array
    {
        return [
            'a range the card does not allow' => ['this_year'],
            'an unknown value' => ['next_century'],
            'no value' => [null],
        ];
    }

    /**
     * EN: A rejected chosen value falls back to the card's default range, because the chosen value comes from the browser and can be changed.
     * AR: القيمة المختارة المرفوضة ترجع للفترة الافتراضية مال الكارد، لأن القيمة تجي من المتصفح وممكن أحد يتلاعب بيها.
     */
    #[DataProvider('rejectedValues')]
    public function test_rejected_chosen_value_falls_back_to_the_default_range(?string $value): void
    {
        $range = MetricRange::resolve($value, [MetricRange::ThisMonth, MetricRange::LastMonth], MetricRange::ThisMonth);

        $this->assertSame(MetricRange::ThisMonth, $range);
    }
}
