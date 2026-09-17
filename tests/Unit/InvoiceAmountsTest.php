<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\SpecificDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAmountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A general invoice's amount is the sum of its line prices, and it is fully paid.
     * AR: مبلغ الفاتورة العامة = مجموع أسعار بنودها، وتنحسب مدفوعة بالكامل.
     */
    public function test_general_invoice_amount_is_the_sum_of_its_lines_and_it_is_fully_paid(): void
    {
        $invoice = Invoice::factory()->create();
        $this->generalLine($invoice, 500);
        $this->generalLine($invoice, 250);

        $invoice = $invoice->fresh();

        $this->assertEquals(750, $invoice->amount);
        $this->assertEquals(750, $invoice->paid_amount);
        $this->assertEquals(0, $invoice->remaining);
    }

    /**
     * EN: A purchase invoice's amount is the sum of its line prices, and all of it remains unpaid.
     * AR: مبلغ فاتورة الشراء = مجموع أسعار بنودها، وكله يبقى باقي (دين).
     */
    public function test_purchase_invoice_amount_is_the_sum_of_its_lines_and_all_of_it_remains(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $this->purchaseLine($invoice, ['price' => 1000]);
        $this->purchaseLine($invoice, ['price' => 400]);

        $invoice = $invoice->fresh();

        $this->assertEquals(1400, $invoice->amount);
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertEquals(1400, $invoice->remaining);
    }

    /**
     * EN: Changing a line's price recalculates the invoice's amount and remaining.
     * AR: تغيير سعر البند يعيد حساب مبلغ الفاتورة والباقي.
     */
    public function test_changing_a_line_price_recalculates_the_invoice(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $line = $this->purchaseLine($invoice, ['price' => 1000]);

        $line->update(['price' => 1200]);

        $invoice = $invoice->fresh();

        $this->assertEquals(1200, $invoice->amount);
        $this->assertEquals(1200, $invoice->remaining);
    }

    /**
     * EN: Deleting a line recalculates the invoice's amount.
     * AR: حذف البند يعيد حساب مبلغ الفاتورة.
     */
    public function test_deleting_a_line_recalculates_the_invoice(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $this->purchaseLine($invoice, ['price' => 1000]);
        $line = $this->purchaseLine($invoice, ['price' => 500]);

        $line->delete();

        $this->assertEquals(1000, $invoice->fresh()->amount);
    }

    /**
     * EN: Creates a general invoice line with the given price, under the invoice's general description.
     * AR: ينشئ بند فاتورة عامة بالسعر المحدد، تحت الوصف العام مال الفاتورة.
     */
    private function generalLine(Invoice $invoice, float $price): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $invoice->general_description_id])->id,
            'price' => $price,
        ]);
    }

    /**
     * EN: Creates a purchase line for a new item: one box of 10 for 1000, unless other values are given.
     * AR: ينشئ بند شراء لمادة جديدة: كارتون واحد بيه 10 بسعر 1000، إلا إذا انطت قيم ثانية.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function purchaseLine(Invoice $invoice, array $attributes = []): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'box_quantity' => 10,
            'price' => 1000,
            ...$attributes,
        ]);
    }
}
