<?php

namespace Tests\Unit;

use App\Metrics\Calculations\InvoiceCount;
use App\Metrics\Period;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Counts only the invoices whose date is inside the period.
     * AR: يحسب بس الفواتير اللي تاريخها داخل الفترة.
     */
    public function test_counts_only_invoices_dated_inside_the_period(): void
    {
        foreach (['2026-03-31', '2026-04-01', '2026-04-30', '2026-05-01'] as $date) {
            Invoice::factory()->create(['date' => $date]);
        }

        $count = (new InvoiceCount)->calculate($this->april());

        $this->assertEquals(2, $count);
    }

    /**
     * EN: Deleted invoices are not counted.
     * AR: الفواتير المحذوفة ما تنحسب.
     */
    public function test_deleted_invoices_are_not_counted(): void
    {
        Invoice::factory()->create(['date' => '2026-04-10']);
        Invoice::factory()->create(['date' => '2026-04-20'])->delete();

        $count = (new InvoiceCount)->calculate($this->april());

        $this->assertEquals(1, $count);
    }

    /**
     * EN: A period without a start and an end counts every invoice.
     * AR: الفترة اللي ما إلها بداية ولا نهاية تحسب كل الفواتير.
     */
    public function test_period_without_a_start_and_an_end_counts_every_invoice(): void
    {
        foreach (['2020-01-01', '2026-04-15', '2030-12-31'] as $date) {
            Invoice::factory()->create(['date' => $date]);
        }

        $count = (new InvoiceCount)->calculate(new Period(null, null));

        $this->assertEquals(3, $count);
    }

    /**
     * EN: The whole of April 2026.
     * AR: شهر نيسان 2026 كامل.
     */
    private function april(): Period
    {
        return new Period(new CarbonImmutable('2026-04-01 00:00:00'), new CarbonImmutable('2026-04-30 23:59:59'));
    }
}
