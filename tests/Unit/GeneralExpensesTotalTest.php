<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Metrics\Calculations\GeneralExpensesTotal;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\SpecificDescription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralExpensesTotalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The general expenses are the sum of the amounts of the output general invoices dated inside the period.
     * AR: المصاريف العامة = مجموع مبالغ الفواتير العامة نوع خارج اللي تاريخها داخل الفترة.
     */
    public function test_general_expenses_are_the_sum_of_output_general_invoices_inside_the_period(): void
    {
        $this->generalInvoice(InvoiceType::Output, '2026-03-31', 1000);
        $this->generalInvoice(InvoiceType::Output, '2026-04-01', 500);
        $this->generalInvoice(InvoiceType::Output, '2026-04-30', 250);
        $this->generalInvoice(InvoiceType::Output, '2026-05-01', 2000);

        $this->assertEquals(750, (new GeneralExpensesTotal)->calculate($this->april()));
    }

    /**
     * EN: Input invoices are not general expenses.
     * AR: فواتير الداخل مو مصاريف عامة.
     */
    public function test_input_invoices_are_not_general_expenses(): void
    {
        $this->generalInvoice(InvoiceType::Output, '2026-04-10', 500);
        $this->generalInvoice(InvoiceType::Input, '2026-04-10', 300);

        $this->assertEquals(500, (new GeneralExpensesTotal)->calculate($this->april()));
    }

    /**
     * EN: Purchase invoices are not general expenses, even what was paid on them.
     * AR: فواتير الشراء مو مصاريف عامة، حتى المدفوع منها.
     */
    public function test_purchase_invoices_are_not_general_expenses(): void
    {
        $this->generalInvoice(InvoiceType::Output, '2026-04-10', 500);

        $purchase = Invoice::factory()->purchase()->create(['date' => '2026-04-10']);
        InvoiceItem::factory()->create([
            'invoice_id' => $purchase->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'box_quantity' => 10,
            'price' => 1000,
        ]);
        Payoff::factory()->create(['invoice_id' => $purchase->id, 'amount' => 300]);

        $this->assertEquals(500, (new GeneralExpensesTotal)->calculate($this->april()));
    }

    /**
     * EN: Deleted invoices are not counted.
     * AR: الفواتير المحذوفة ما تنحسب.
     */
    public function test_deleted_invoices_are_not_counted(): void
    {
        $this->generalInvoice(InvoiceType::Output, '2026-04-10', 500);
        $this->generalInvoice(InvoiceType::Output, '2026-04-20', 250)->delete();

        $this->assertEquals(500, (new GeneralExpensesTotal)->calculate($this->april()));
    }

    /**
     * EN: A period without a start and an end sums the output general invoices of every date.
     * AR: الفترة اللي ما إلها بداية ولا نهاية تجمع الفواتير العامة نوع خارج بكل التواريخ.
     */
    public function test_period_without_a_start_and_an_end_sums_every_date(): void
    {
        $this->generalInvoice(InvoiceType::Output, '2020-01-01', 100);
        $this->generalInvoice(InvoiceType::Output, '2030-12-31', 200);

        $this->assertEquals(300, (new GeneralExpensesTotal)->calculate(new Period(null, null)));
    }

    /**
     * EN: Creates a general invoice of the given type and date, with one line of the given price.
     * AR: ينشئ فاتورة عامة بالنوع والتاريخ المحددين، بيها بند واحد بالسعر المحدد.
     */
    private function generalInvoice(InvoiceType $type, string $date, float $price): Invoice
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
