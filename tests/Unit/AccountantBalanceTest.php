<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Metrics\Calculations\AccountantBalance;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\SpecificDescription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountantBalanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The accountant's box is all the revenues minus the expenses.
     * AR: صندوق المحاسب = كل الإيرادات ناقص المصاريف.
     */
    public function test_box_is_the_revenues_minus_the_expenses(): void
    {
        $this->invoice(InvoiceType::Input, '2026-04-10', 1000);
        $this->invoice(InvoiceType::Output, '2026-04-10', 700);

        $this->assertEquals(300, (new AccountantBalance)->calculate($this->april()));
    }

    /**
     * EN: Company purchase invoices reduce the box only by what was paid on them, not by their amount.
     * AR: فواتير مشتريات الشركات تنقص الصندوق بس بالمدفوع منها، مو بمبلغها.
     */
    public function test_purchase_invoices_reduce_the_box_by_their_paid_amount(): void
    {
        $this->invoice(InvoiceType::Input, '2026-04-10', 1000);
        $this->invoice(InvoiceType::Output, '2026-04-10', 700);

        $purchase = Invoice::factory()->purchase()->create(['date' => '2026-04-10']);
        InvoiceItem::factory()->create([
            'invoice_id' => $purchase->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 10,
            'price' => 5000,
        ]);
        Payoff::factory()->create(['invoice_id' => $purchase->id, 'amount' => 200]);

        $this->assertEquals(100, (new AccountantBalance)->calculate($this->april()));
    }

    /**
     * EN: The box is negative when the expenses are more than the revenues.
     * AR: الصندوق يطلع بالسالب إذا المصاريف أكثر من الإيرادات.
     */
    public function test_box_is_negative_when_expenses_are_more_than_revenues(): void
    {
        $this->invoice(InvoiceType::Input, '2026-04-10', 200);
        $this->invoice(InvoiceType::Output, '2026-04-10', 700);

        $this->assertEquals(-500, (new AccountantBalance)->calculate($this->april()));
    }

    /**
     * EN: Only the invoices dated inside the period are counted.
     * AR: تنحسب بس الفواتير اللي تاريخها داخل الفترة.
     */
    public function test_only_invoices_inside_the_period_are_counted(): void
    {
        $this->invoice(InvoiceType::Input, '2026-04-10', 1000);
        $this->invoice(InvoiceType::Output, '2026-04-10', 700);
        $this->invoice(InvoiceType::Input, '2026-03-31', 5000);
        $this->invoice(InvoiceType::Output, '2026-05-01', 9000);

        $this->assertEquals(300, (new AccountantBalance)->calculate($this->april()));
    }

    /**
     * EN: A period without a start and an end counts the invoices of every date.
     * AR: الفترة اللي ما إلها بداية ولا نهاية تحسب الفواتير بكل التواريخ.
     */
    public function test_period_without_a_start_and_an_end_counts_every_date(): void
    {
        $this->invoice(InvoiceType::Input, '2020-01-01', 1000);
        $this->invoice(InvoiceType::Output, '2030-12-31', 400);

        $this->assertEquals(600, (new AccountantBalance)->calculate(new Period(null, null)));
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
