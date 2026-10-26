<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCbmTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given a purchase invoice with a line of 4 cartons of 0.25 CBM and a line of 3 cartons of 0.5 CBM,
     *     then the invoice's CBM is the sum of its lines: 1 + 1.5 = 2.5.
     * AR: فاتورة شراء بيها بند 4 كراتين حجم الوحدة 0.25 CBM وبند 3 كراتين حجم الوحدة 0.5 CBM،
     *     فـ CBM الفاتورة = مجموع بنودها: 1 + 1.5 = 2.5.
     */
    public function test_invoice_cbm_is_the_sum_of_its_lines_cbm(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        $this->addLine($invoice, cartonsCount: 4, cartonCbm: 0.25);
        $this->addLine($invoice, cartonsCount: 3, cartonCbm: 0.5);

        $this->assertEquals(2.5, $invoice->fresh()->cbm);
    }

    /**
     * EN: Given an invoice of 2.5 CBM, when the line of 4 cartons gets 8 packets (one per carton), then it is 8 cartons and the invoice's CBM becomes 3.5.
     * AR: فاتورة بـ 2.5 CBM، بند الـ 4 كراتين يصير بيه 8 باكيتات (باكيت بكل كارتونة)، فيصير 8 كراتين و CBM الفاتورة يصير 3.5.
     */
    public function test_editing_a_line_recalculates_the_invoice_cbm(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $line = $this->addLine($invoice, cartonsCount: 4, cartonCbm: 0.25);
        $this->addLine($invoice, cartonsCount: 3, cartonCbm: 0.5);

        $line->update(['boxes_count' => 8]);

        $this->assertEquals(3.5, $invoice->fresh()->cbm);
    }

    /**
     * EN: Given an invoice of 2.5 CBM, when the line of 1.5 CBM is deleted, then the invoice's CBM becomes 1.
     * AR: فاتورة بـ 2.5 CBM، ينحذف البند اللي حجمه 1.5 CBM، فـ CBM الفاتورة يصير 1.
     */
    public function test_deleting_a_line_takes_its_cbm_off_the_invoice(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $this->addLine($invoice, cartonsCount: 4, cartonCbm: 0.25);
        $line = $this->addLine($invoice, cartonsCount: 3, cartonCbm: 0.5);

        $line->delete();

        $this->assertEquals(1, $invoice->fresh()->cbm);
    }

    /**
     * EN: Adds a purchase line for a new item with the given cartons and CBM of one carton: one packet of 10 per carton, for 1000.
     * AR: يضيف بند شراء لمادة جديدة بعدد الكراتين وحجم الكارتونة المحددين: باكيت واحد بكل كارتونة بيه 10 قطع، بسعر 1000.
     */
    private function addLine(Invoice $invoice, float $cartonsCount, float $cartonCbm): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => $cartonsCount,
            'pieces_per_packet' => 10,
            'boxes_per_carton' => 1,
            'carton_cbm' => $cartonCbm,
            'price' => 1000,
        ]);
    }
}
