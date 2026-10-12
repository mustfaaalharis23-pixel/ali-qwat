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
     * EN: A purchase line's cartons are its packets ÷ the packets one carton holds: 100 packets, 10 per carton, are 10 cartons.
     *     A typed carton count is ignored, and the CBM follows the calculated cartons: 10 × 0.05 = 0.5.
     * AR: عدد كراتين بند الشراء = عدد الباكيتات ÷ تعبئة الكارتونة: 100 باكيت، 10 بالكارتونة، يعني 10 كراتين.
     *     عدد الكراتين المكتوب باليد ما ينحسب، والـ CBM يمشي على الكراتين المحسوبة: 10 × 0.05 = 0.5.
     */
    public function test_purchase_line_cartons_are_packets_over_packets_per_carton(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 100,
            'boxes_per_carton' => 10,
            'cartons_count' => 3,
            'carton_cbm' => 0.05,
        ])->fresh();

        $this->assertEquals(10, $line->cartons_count);
        $this->assertEqualsWithDelta(0.5, $line->cbm, 0.0001);
    }

    /**
     * EN: A carton that is not full is still a carton: 105 packets, 10 per carton, are 11 cartons.
     * AR: الكارتونة الناقصة تنحسب كارتونة: 105 باكيت، 10 بالكارتونة، يعني 11 كارتونة.
     */
    public function test_a_partly_filled_carton_counts_as_a_whole_carton(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 105,
            'boxes_per_carton' => 10,
            'carton_cbm' => 0.05,
        ]);

        $this->assertEquals(11, $line->fresh()->cartons_count);
    }

    /**
     * EN: Given a line of 100 packets, 10 per carton (10 cartons of 0.05 CBM), when its packets become 50,
     *     then its cartons become 5 and its CBM 0.25.
     * AR: بند بيه 100 باكيت، 10 بالكارتونة (10 كراتين حجم الوحدة 0.05 CBM)، تصير الباكيتات 50،
     *     فتصير الكراتين 5 والـ CBM يصير 0.25.
     */
    public function test_editing_the_packets_recalculates_the_cartons_and_cbm(): void
    {
        $line = $this->purchaseLine(Invoice::factory()->purchase()->create(), Item::factory()->create(), [
            'boxes_count' => 100,
            'boxes_per_carton' => 10,
            'carton_cbm' => 0.05,
        ]);

        $line->update(['boxes_count' => 50]);

        $this->assertEquals(5, $line->fresh()->cartons_count);
        $this->assertEqualsWithDelta(0.25, $line->fresh()->cbm, 0.0001);
    }

    /**
     * EN: A general invoice line takes no space: it has no carton packing, no cartons and no carton CBM, and its CBM is zero whatever is given.
     * AR: بند الفاتورة العامة ما ياخذ مساحة: ما إله تعبئة كارتونة ولا كراتين ولا حجم كارتونة، والـ CBM مالته صفر شنو ما انطى.
     */
    public function test_general_invoice_line_has_no_cbm(): void
    {
        $invoice = Invoice::factory()->create();

        $line = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $invoice->general_description_id])->id,
            'price' => 500,
            'boxes_per_carton' => 6,
            'cartons_count' => 3,
            'carton_cbm' => 0.5,
        ])->fresh();

        $this->assertNull($line->boxes_per_carton);
        $this->assertNull($line->cartons_count);
        $this->assertNull($line->carton_cbm);
        $this->assertEquals(0, $line->cbm);
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
