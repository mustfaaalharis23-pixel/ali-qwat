<?php

namespace Tests\Unit;

use App\Invoices\RevertInvoiceConfirmation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemStockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A pending purchase invoice does not change the item's available quantity or cost.
     * AR: فاتورة الشراء المعلّقة ما تغير كمية المادة المتوفرة ولا كلفتها.
     */
    public function test_pending_purchase_invoice_does_not_change_the_item_stock_or_cost(): void
    {
        $item = Item::factory()->create();

        $this->purchaseLine(Invoice::factory()->purchase()->create(), $item);

        $item = $item->fresh();

        $this->assertEquals(0, $item->available_quantity);
        $this->assertEquals(0, $item->incoming_quantity);
        $this->assertEquals(0, $item->cost_price);
    }

    /**
     * EN: An invoice the importer confirmed is stock that did not arrive yet: it is the item's incoming quantity, not its available quantity, and it does not set the cost.
     * AR: الفاتورة اللي أكدها المستورد كميتها موجودة بس ما وصلت: تنحسب كمية قادمة للمادة، مو كمية متوفرة، وما تحدد الكلفة.
     */
    public function test_invoice_the_importer_confirmed_is_incoming_quantity(): void
    {
        $item = Item::factory()->create();

        $this->purchaseLine(Invoice::factory()->purchase()->importerConfirmed()->create(), $item);

        $item = $item->fresh();

        $this->assertEquals(10, $item->incoming_quantity);
        $this->assertEquals(0, $item->available_quantity);
        $this->assertEquals(0, $item->cost_price);
    }

    /**
     * EN: A purchase invoice both sides confirmed adds its quantity to the item, leaves nothing incoming, and sets the item's cost to the unit price.
     * AR: فاتورة الشراء المؤكدة من الجهتين تضيف كميتها للمادة، وما تبقي كمية قادمة، وكلفة المادة تصير سعر الوحدة.
     */
    public function test_confirmed_purchase_invoice_adds_the_quantity_and_sets_the_cost(): void
    {
        $item = Item::factory()->create();

        $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item, [
            'boxes_count' => 2,
            'box_quantity' => 12,
            'price' => 2400,
        ]);

        $item = $item->fresh();

        $this->assertEquals(24, $item->available_quantity);
        $this->assertEquals(0, $item->incoming_quantity);
        $this->assertEquals(100, $item->cost_price);
    }

    /**
     * EN: A later confirmed invoice sets the item's cost, and the previous cost is kept as the last cost.
     * AR: الفاتورة المؤكدة الأحدث تحدد كلفة المادة، والكلفة السابقة تنحفظ ككلفة أخيرة.
     */
    public function test_later_confirmed_invoice_sets_the_cost_and_keeps_the_last_cost(): void
    {
        $item = Item::factory()->create();

        $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item, ['price' => 1000]);
        $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item, ['price' => 1500]);

        $item = $item->fresh();

        $this->assertEquals(20, $item->available_quantity);
        $this->assertEquals(150, $item->cost_price);
        $this->assertEquals(100, $item->last_cost_price);
    }

    /**
     * EN: Deleting a confirmed invoice removes its quantity from the item and brings back the cost of the invoice before it.
     * AR: حذف فاتورة مؤكدة يشيل كميتها من المادة، ويرجع كلفة الفاتورة اللي قبلها.
     */
    public function test_deleting_a_confirmed_invoice_removes_its_stock_and_restores_the_cost(): void
    {
        $item = Item::factory()->create();
        $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item, ['price' => 1000]);
        $laterInvoice = Invoice::factory()->purchase()->confirmed()->create();
        $this->purchaseLine($laterInvoice, $item, ['price' => 1500]);

        $laterInvoice->delete();

        $item = $item->fresh();

        $this->assertEquals(10, $item->available_quantity);
        $this->assertEquals(100, $item->cost_price);
    }

    /**
     * EN: The item's cost is rounded to two decimal places.
     * AR: كلفة المادة تتقرب لمنزلتين عشريتين.
     */
    public function test_cost_is_rounded_to_two_decimals(): void
    {
        $item = Item::factory()->create();

        $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item, [
            'box_quantity' => 3,
            'price' => 100,
        ]);

        $this->assertEquals(33.33, $item->fresh()->cost_price);
    }

    /**
     * EN: Selling takes the sold quantity out of the item's available quantity.
     * AR: البيع ينقص الكمية المباعة من كمية المادة المتوفرة.
     */
    public function test_selling_takes_the_quantity_out_of_the_available_quantity(): void
    {
        $item = Item::factory()->stocked(10)->create();

        OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 4, 'price' => 1000]);

        $this->assertEquals(6, $item->fresh()->available_quantity);
    }

    /**
     * EN: Deleting a sold line gives its quantity back to the item.
     * AR: حذف بند مبيع يرجّع كميته للمادة.
     */
    public function test_deleting_a_sold_line_gives_the_quantity_back(): void
    {
        $item = Item::factory()->stocked(10)->create();
        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 4, 'price' => 1000]);
        OrderItem::factory()->create(['order_id' => $line->order_id, 'item_id' => $item->id, 'quantity' => 1, 'price' => 1000]);

        $line->delete();

        $this->assertEquals(9, $item->fresh()->available_quantity);
    }

    /**
     * EN: Taking back the receiver's confirmation turns the available quantity back into an incoming quantity.
     * AR: التراجع عن تأكيد المستلم يرجّع الكمية المتوفرة كمية قادمة.
     */
    public function test_reverting_the_receiver_confirmation_turns_the_quantity_back_to_incoming(): void
    {
        $item = Item::factory()->create();
        $line = $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item);

        (new RevertInvoiceConfirmation)->handle(User::factory()->create(['is_super' => true]), $line->invoice);

        $item = $item->fresh();

        $this->assertEquals(0, $item->available_quantity);
        $this->assertEquals(10, $item->incoming_quantity);
    }

    /**
     * EN: Moving a purchase line to another item moves the quantity with it: the first item loses it and the second item gets it.
     * AR: تبديل مادة بند الشراء ينقل الكمية وياها: المادة الأولى تخسرها والثانية تاخذها.
     */
    public function test_moving_a_purchase_line_to_another_item_moves_the_quantity(): void
    {
        $first = Item::factory()->create();
        $second = Item::factory()->create();
        $line = $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $first);

        $line->update(['specific_description_id' => $second->specificDescription->id]);

        $this->assertEquals(0, $first->fresh()->available_quantity);
        $this->assertEquals(10, $second->fresh()->available_quantity);
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
