<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Metrics\Calculations\RevenuesTotal;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SpecificDescription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenuesTotalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The revenues are the sum of the amounts of all the input invoices dated inside the period.
     * AR: الإيرادات = مجموع مبالغ كل فواتير الداخل اللي تاريخها داخل الفترة.
     */
    public function test_revenues_are_the_sum_of_input_invoices_inside_the_period(): void
    {
        $this->invoice(InvoiceType::Input, '2026-03-31', 1000);
        $this->invoice(InvoiceType::Input, '2026-04-01', 500);
        $this->invoice(InvoiceType::Input, '2026-04-30', 250);
        $this->invoice(InvoiceType::Input, '2026-05-01', 2000);

        $this->assertEquals(750, (new RevenuesTotal)->calculate($this->april()));
    }

    /**
     * EN: Output invoices are not revenues.
     * AR: فواتير الخارج مو إيرادات.
     */
    public function test_output_invoices_are_not_revenues(): void
    {
        $this->invoice(InvoiceType::Input, '2026-04-10', 500);
        $this->invoice(InvoiceType::Output, '2026-04-10', 300);

        $this->assertEquals(500, (new RevenuesTotal)->calculate($this->april()));
    }

    /**
     * EN: Deleted invoices are not counted.
     * AR: الفواتير المحذوفة ما تنحسب.
     */
    public function test_deleted_invoices_are_not_counted(): void
    {
        $this->invoice(InvoiceType::Input, '2026-04-10', 500);
        $this->invoice(InvoiceType::Input, '2026-04-20', 250)->delete();

        $this->assertEquals(500, (new RevenuesTotal)->calculate($this->april()));
    }

    /**
     * EN: Without invoices, the revenues are zero.
     * AR: إذا ماكو فواتير، الإيرادات صفر.
     */
    public function test_revenues_are_zero_without_invoices(): void
    {
        $this->assertEquals(0, (new RevenuesTotal)->calculate($this->april()));
    }

    /**
     * EN: A period without a start and an end sums the input invoices of every date.
     * AR: الفترة اللي ما إلها بداية ولا نهاية تجمع فواتير الداخل بكل التواريخ.
     */
    public function test_period_without_a_start_and_an_end_sums_every_date(): void
    {
        $this->invoice(InvoiceType::Input, '2020-01-01', 100);
        $this->invoice(InvoiceType::Input, '2030-12-31', 200);

        $this->assertEquals(300, (new RevenuesTotal)->calculate(new Period(null, null)));
    }

    /**
     * EN: Creates a general invoice of the given type and date, with one line of the given price.
     * AR: ينشئ فاتورة عامة بالنوع والتاريخ المحددين، بيها بند واحد بالسعر المحدد.
     */
    private function invoice(InvoiceType $type, string $date, float $price): Invoice
    {
        $invoice = Invoice::factory()->create(['type' => $type, 'date' => $date]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $invoice->general_description_id])->id,
            'price' => $price,
        ]);

        return $invoice->fresh();
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
