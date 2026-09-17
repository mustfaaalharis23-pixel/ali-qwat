<?php

namespace Tests\Unit;

use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\SpecificDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A general invoice line is always one unit in one box, and has no item.
     * AR: بند الفاتورة العامة دائماً وحدة وحدة بكارتون واحد، وبدون مادة.
     */
    public function test_general_invoice_line_is_one_unit_in_one_box_without_an_item(): void
    {
        $invoice = Invoice::factory()->create();

        $line = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $invoice->general_description_id])->id,
            'price' => 500,
            'quantity' => 7,
            'boxes_count' => 3,
            'box_quantity' => 4,
        ])->fresh();

        $this->assertEquals(1, $line->quantity);
        $this->assertEquals(1, $line->boxes_count);
        $this->assertEquals(1, $line->box_quantity);
        $this->assertNull($line->item_id);
    }

    /**
     * EN: A purchase invoice line gets the item of its specific description.
     * AR: بند فاتورة الشراء ياخذ مادة وصفه الخاص.
     */
    public function test_purchase_line_gets_the_item_of_its_description(): void
    {
        $item = Item::factory()->create();

        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), $item);

        $this->assertSame($item->id, $line->fresh()->item_id);
    }

    /**
     * EN: A line's specific description must be under the invoice's general description.
     * AR: الوصف الخاص للبند لازم يكون تحت الوصف العام مال الفاتورة.
     */
    public function test_line_description_must_be_under_the_invoice_general_description(): void
    {
        $invoice = Invoice::factory()->create();
        $descriptionOfAnotherGeneral = SpecificDescription::factory()->create();

        $this->expectException(ValidationException::class);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $descriptionOfAnotherGeneral->id,
            'price' => 500,
        ]);
    }

    /**
     * EN: A purchase invoice line needs the specific description of an item.
     * AR: بند فاتورة الشراء يحتاج وصف خاص مال مادة.
     */
    public function test_purchase_line_needs_an_item_description(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $descriptionWithoutItem = SpecificDescription::factory()->create([
            'general_description_id' => GeneralDescription::purchases()->id,
        ]);

        $this->expectException(ValidationException::class);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $descriptionWithoutItem->id,
            'boxes_count' => 1,
            'box_quantity' => 10,
            'price' => 1000,
        ]);
    }

    /**
     * EN: A purchase line's quantity is the number of boxes times the box quantity.
     * AR: كمية بند الشراء = عدد الكراتين × سعة الكارتون.
     */
    public function test_purchase_line_quantity_is_boxes_times_box_quantity(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 2,
            'box_quantity' => 12,
        ]);

        $this->assertEquals(24, $line->fresh()->quantity);
    }

    /**
     * EN: Deleting a line is a soft delete: the row stays in the table and the deletion time is saved.
     * AR: حذف البند يكون soft delete: السطر يبقى بالجدول وينحفظ وقت الحذف.
     */
    public function test_deleting_a_line_soft_deletes_it(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create());

        $line->delete();

        $this->assertSoftDeleted($line);
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
