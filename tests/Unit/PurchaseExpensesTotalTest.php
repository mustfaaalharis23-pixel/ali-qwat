<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Metrics\Calculations\PurchaseExpensesTotal;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\SpecificDescription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseExpensesTotalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The purchase expenses are the sum of what was paid on the purchase invoices dated inside the period, not their amounts.
     * AR: مصاريف المشتريات = مجموع المدفوع على فواتير الشراء اللي تاريخها داخل الفترة، مو مبالغها.
     */
    public function test_purchase_expenses_are_the_sum_paid_on_purchase_invoices_inside_the_period(): void
    {
        $this->purchaseInvoice('2026-03-31', 1000, 100);
        $this->purchaseInvoice('2026-04-01', 1000, 300);
        $this->purchaseInvoice('2026-04-30', 1000, 200);
        $this->purchaseInvoice('2026-05-01', 1000, 400);

        $this->assertEquals(500, (new PurchaseExpensesTotal)->calculate($this->april()));
    }

    /**
     * EN: Output general invoices and input invoices are not purchase expenses.
     * AR: الفواتير العامة نوع خارج وفواتير الداخل مو مصاريف مشتريات.
     */
    public function test_general_invoices_are_not_purchase_expenses(): void
    {
        $this->purchaseInvoice('2026-04-10', 1000, 300);
        $this->generalInvoice(InvoiceType::Output, '2026-04-10', 500);
        $this->generalInvoice(InvoiceType::Input, '2026-04-10', 700);

        $this->assertEquals(300, (new PurchaseExpensesTotal)->calculate($this->april()));
    }

    /**
     * EN: Creates a purchase invoice of the given date with one line of the given price, and a payoff of the given amount.
     * AR: ينشئ فاتورة شراء بالتاريخ المحدد بيها بند واحد بالسعر المحدد، وتسديد بالمبلغ المحدد.
     */
    private function purchaseInvoice(string $date, float $price, float $paid): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create(['date' => $date]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 10,
            'price' => $price,
        ]);
        Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => $paid]);

        return $invoice->fresh();
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
