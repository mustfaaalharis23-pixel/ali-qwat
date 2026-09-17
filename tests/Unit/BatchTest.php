<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\SpecificDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A purchase line creates one batch with its item, invoice, quantity, and unit price (line price / quantity).
     * AR: بند الشراء ينشئ دفعة وحدة بيها مادته وفاتورته وكميته وسعر الوحدة (سعر البند / الكمية).
     */
    public function test_purchase_line_creates_a_batch_with_the_unit_price(): void
    {
        $item = Item::factory()->create();
        $invoice = Invoice::factory()->purchase()->create();

        $line = $this->purchaseLine($invoice, $item, [
            'boxes_count' => 2,
            'box_quantity' => 12,
            'price' => 2400,
        ]);

        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();

        $this->assertSame($item->id, $batch->item_id);
        $this->assertSame($invoice->id, $batch->invoice_id);
        $this->assertEquals(24, $batch->quantity);
        $this->assertEquals(100, $batch->purchase_price);
    }

    /**
     * EN: A general invoice line creates no batch.
     * AR: بند الفاتورة العامة ما ينشئ دفعة.
     */
    public function test_general_invoice_line_creates_no_batch(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $invoice->general_description_id])->id,
            'price' => 500,
        ]);

        $this->assertSame(0, Batch::query()->count());
    }

    /**
     * EN: Updating a line updates its batch, without creating a second batch.
     * AR: تعديل البند يعدل دفعته، بدون ما ينشئ دفعة ثانية.
     */
    public function test_updating_a_line_updates_its_batch(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create());

        $line->update(['boxes_count' => 2, 'price' => 3000]);

        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();

        $this->assertEquals(20, $batch->quantity);
        $this->assertEquals(150, $batch->purchase_price);
    }

    /**
     * EN: Deleting a line soft deletes its batch.
     * AR: حذف البند يحذف دفعته soft delete.
     */
    public function test_deleting_a_line_soft_deletes_its_batch(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create());
        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();

        $line->delete();

        $this->assertSoftDeleted($batch);
    }

    /**
     * EN: Creates a purchase line for the item: one box of 10 for 1000, unless other values are given.
     * AR: ينشئ بند شراء للمادة: كارتون واحد بيه 10 بسعر 1000، إلا إذا انطت قيم ثانية.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function purchaseLine(Invoice $invoice, Item $item, array $attributes = []): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 1,
            'box_quantity' => 10,
            'price' => 1000,
            ...$attributes,
        ]);
    }
}
