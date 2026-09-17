<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Deleting an invoice soft deletes its lines and their batches.
     * AR: حذف الفاتورة يحذف بنودها ودفعاتهن soft delete.
     */
    public function test_deleting_an_invoice_soft_deletes_its_lines_and_batches(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $line = $this->purchaseLine($invoice, Item::factory()->create());
        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();

        $invoice->delete();

        $this->assertSoftDeleted($line);
        $this->assertSoftDeleted($batch);
    }

    /**
     * EN: Restoring an invoice restores its lines and batches, so its amount and the item's stock come back.
     * AR: استرجاع الفاتورة يرجّع بنودها ودفعاتها، فيرجع مبلغها ومخزون المادة.
     */
    public function test_restoring_an_invoice_restores_its_lines_batches_amount_and_stock(): void
    {
        $item = Item::factory()->create();
        $invoice = Invoice::factory()->purchase()->confirmed()->create();
        $line = $this->purchaseLine($invoice, $item);
        $batch = Batch::query()->where('invoice_item_id', $line->id)->sole();
        $invoice->delete();

        $invoice->restore();

        $this->assertNotSoftDeleted($line);
        $this->assertNotSoftDeleted($batch);
        $this->assertEquals(1000, $invoice->fresh()->amount);
        $this->assertEquals(10, $item->fresh()->available_quantity);
    }

    /**
     * EN: Restoring an invoice keeps the lines that were deleted before the invoice was deleted.
     * AR: استرجاع الفاتورة ما يرجّع البنود اللي انحذفت قبل حذف الفاتورة.
     */
    public function test_restoring_an_invoice_keeps_lines_deleted_before_it(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $this->purchaseLine($invoice, Item::factory()->create(), ['price' => 1000]);
        $deletedEarlier = $this->purchaseLine($invoice, Item::factory()->create(), ['price' => 500]);
        $deletedEarlier->delete();
        $this->travel(1)->minutes();
        $invoice->delete();

        $invoice->restore();

        $this->assertSoftDeleted($deletedEarlier);
        $this->assertEquals(1000, $invoice->fresh()->amount);
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
