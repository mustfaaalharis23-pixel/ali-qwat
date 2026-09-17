<?php

namespace Tests\Unit;

use App\Metrics\Period;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A period keeps only the rows whose date is inside it, including its first and last day.
     * AR: الفترة تخلي بس السطور اللي تاريخها داخلها، ومن ضمنها أول يوم وآخر يوم.
     */
    public function test_period_keeps_only_rows_inside_it_including_its_first_and_last_day(): void
    {
        foreach (['2026-03-31', '2026-04-01', '2026-04-15', '2026-04-30', '2026-05-01'] as $date) {
            Invoice::factory()->create(['date' => $date]);
        }

        $period = new Period(new CarbonImmutable('2026-04-01 00:00:00'), new CarbonImmutable('2026-04-30 23:59:59'));

        $dates = $period->applyTo(Invoice::query(), 'date')
            ->orderBy('date')
            ->pluck('date')
            ->map->toDateString()
            ->all();

        $this->assertSame(['2026-04-01', '2026-04-15', '2026-04-30'], $dates);
    }

    /**
     * EN: A period works on a date and time column: a row late on the last day is inside, and a row at midnight after it is outside.
     * AR: الفترة تشتغل على عمود تاريخ ووقت: سطر بآخر اليوم الأخير داخلها، وسطر بمنتصف الليل بعده برّاها.
     */
    public function test_period_works_on_a_date_and_time_column(): void
    {
        $this->travelTo('2026-04-30 18:00:00');
        $inside = Invoice::factory()->create();
        $this->travelTo('2026-05-01 00:00:00');
        Invoice::factory()->create();

        $period = new Period(new CarbonImmutable('2026-04-01 00:00:00'), new CarbonImmutable('2026-04-30 23:59:59'));

        $this->assertSame([$inside->id], $period->applyTo(Invoice::query(), 'created_at')->pluck('id')->all());
    }

    /**
     * EN: A period without a start and an end keeps all the rows.
     * AR: الفترة اللي ما إلها بداية ولا نهاية تخلي كل السطور.
     */
    public function test_period_without_a_start_and_an_end_keeps_all_rows(): void
    {
        foreach (['2020-01-01', '2026-04-15', '2030-12-31'] as $date) {
            Invoice::factory()->create(['date' => $date]);
        }

        $period = new Period(null, null);

        $this->assertSame(3, $period->applyTo(Invoice::query(), 'date')->count());
    }
}
