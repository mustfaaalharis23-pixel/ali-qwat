<?php

namespace Tests\Unit;

use App\Invoices\RevertInvoiceConfirmation;
use App\Models\Color;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
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
     * EN: A purchase invoice both sides confirmed adds its quantity to the item, leaves nothing incoming, and sets the item's cost.
     *     Given 2 packets of 144 pieces (288 pieces, 24 dozens) for 2400: the item has 24 dozens, at 100 a dozen.
     * AR: فاتورة الشراء المؤكدة من الجهتين تضيف كميتها للمادة، وما تبقي كمية قادمة، وتحدد كلفة المادة.
     *     باكيتين كل واحد 144 قطعة (288 قطعة، يعني 24 درزن) بـ 2400: المادة يصير بيها 24 درزن، والدرزن بـ 100.
     */
    public function test_confirmed_purchase_invoice_adds_the_quantity_and_sets_the_cost(): void
    {
        $item = Item::factory()->create();

        $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item, [
            'boxes_count' => 2,
            'pieces_per_packet' => 144,
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
     * EN: The item's cost of a dozen is rounded to two decimal places: 36 pieces (3 dozens) for 100 is 33.33 a dozen.
     * AR: كلفة الدرزن للمادة تتقرب لمنزلتين عشريتين: 36 قطعة (3 درازن) بـ 100 يعني الدرزن بـ 33.33.
     */
    public function test_cost_is_rounded_to_two_decimals(): void
    {
        $item = Item::factory()->create();

        $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create(), $item, [
            'pieces_per_packet' => 36,
            'price' => 100,
        ]);

        $this->assertEquals(33.33, $item->fresh()->cost_price);
    }

    /**
     * EN: Selling takes the sold quantity out of the item's available quantity: 4 dozens sold of 10 leave 6 dozens.
     * AR: البيع ينقص الكمية المباعة من كمية المادة المتوفرة: بيع 4 درازن من 10 يبقي 6 درازن.
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
     * EN: Given one item bought in packets of 12 pieces at several powers:
     *     on a confirmed invoice, one line of 160 packets spread as 100 packets of power 0.50 and 60 of power 1.00,
     *     and another line of 5 packets without a power;
     *     on an invoice only the importer confirmed, a line of 40 packets of power 1.00;
     *     on a confirmed invoice that was deleted, a line of 10 packets of power 0.50.
     *     Then, power by power from the lowest, with the packets without a power last:
     *     0.50 arrived 100 packets (1200 pieces); 1.00 arrived 60 packets (720 pieces) and has 40 packets (480 pieces) on the way;
     *     without a power arrived 5 packets (60 pieces). The deleted invoice is not counted.
     *     Nothing was sold, so what is left of each power is what arrived of it.
     * AR: مادة وحدة انشترت بباكيتات 12 قطعة وبأكثر من درجة:
     *     بفاتورة مؤكدة، بند واحد بيه 160 باكيت موزعة 100 باكيت درجة 0.50 و60 باكيت درجة 1.00،
     *     وبند ثاني بيه 5 باكيتات بلا درجة؛
     *     وبفاتورة أكدها المستورد بس، بند بيه 40 باكيت درجة 1.00؛
     *     وبفاتورة مؤكدة انحذفت، بند بيه 10 باكيتات درجة 0.50.
     *     فدرجة درجة من الأصغر، واللي بلا درجة بالأخير:
     *     0.50 وصل منها 100 باكيت (1200 قطعة)؛ 1.00 وصل منها 60 باكيت (720 قطعة) وبالطريق 40 باكيت (480 قطعة)؛
     *     واللي بلا درجة وصل منه 5 باكيتات (60 قطعة). الفاتورة المحذوفة ما تنحسب.
     *     وما انباع شي، فالباقي من كل درجة هو اللي وصل منها.
     */
    public function test_the_item_knows_what_came_in_of_each_power(): void
    {
        $item = Item::factory()->create();
        $lowPower = LensPower::factory()->create(['value' => 0.5]);
        $highPower = LensPower::factory()->create(['value' => 1]);

        $confirmed = Invoice::factory()->purchase()->confirmed()->create();
        $this->purchaseLine($confirmed, $item, ['boxes_count' => 160, 'pieces_per_packet' => 12])->groups()->createMany([
            ['lens_power_id' => $highPower->id, 'boxes_count' => 60],
            ['lens_power_id' => $lowPower->id, 'boxes_count' => 100],
        ]);
        $this->purchaseLine($confirmed, $item, ['boxes_count' => 5, 'pieces_per_packet' => 12]);

        $onTheWay = Invoice::factory()->purchase()->importerConfirmed()->create();
        $this->purchaseLine($onTheWay, $item, ['boxes_count' => 40, 'pieces_per_packet' => 12])->groups()->create([
            'lens_power_id' => $highPower->id,
            'boxes_count' => 40,
        ]);

        $deleted = Invoice::factory()->purchase()->confirmed()->create();
        $this->purchaseLine($deleted, $item, ['boxes_count' => 10, 'pieces_per_packet' => 12])->groups()->create([
            'lens_power_id' => $lowPower->id,
            'boxes_count' => 10,
        ]);
        $deleted->delete();

        $this->assertEquals([
            ['power' => '0.50', 'arrived_packets' => 100, 'arrived_pieces' => 1200, 'incoming_packets' => 0, 'incoming_pieces' => 0, 'remaining_pieces' => 1200],
            ['power' => '1.00', 'arrived_packets' => 60, 'arrived_pieces' => 720, 'incoming_packets' => 40, 'incoming_pieces' => 480, 'remaining_pieces' => 720],
            ['power' => null, 'arrived_packets' => 5, 'arrived_pieces' => 60, 'incoming_packets' => 0, 'incoming_pieces' => 0, 'remaining_pieces' => 60],
        ], $item->quantitiesByPower()->all());
    }

    /**
     * EN: Given one line of 15 packets of 12 pieces that arrived, spread as 10 packets of power 0.50 (120 pieces) and 5 of power 1.00 (60 pieces),
     *     and 20 pieces of 0.50 sold.
     *     Then 100 pieces of 0.50 are left, and all 60 pieces of 1.00: a sale is taken out of its own power only.
     * AR: وصل بند واحد بيه 15 باكيت، كل باكيت 12 قطعة، موزع 10 باكيتات درجة 0.50 (120 قطعة) و5 باكيتات درجة 1.00 (60 قطعة)،
     *     وانباع 20 قطعة من 0.50.
     *     فيبقى 100 قطعة من 0.50، وكل الـ 60 قطعة مال 1.00: البيع ينقص من درجته بس.
     */
    public function test_what_is_left_of_each_power_takes_out_what_was_sold_of_it(): void
    {
        $item = Item::factory()->create();
        $lowPower = LensPower::factory()->create(['value' => 0.5]);
        $highPower = LensPower::factory()->create(['value' => 1]);
        $confirmed = Invoice::factory()->purchase()->confirmed()->create();
        $this->purchaseLine($confirmed, $item, ['boxes_count' => 15, 'pieces_per_packet' => 12])->groups()->createMany([
            ['lens_power_id' => $lowPower->id, 'boxes_count' => 10],
            ['lens_power_id' => $highPower->id, 'boxes_count' => 5],
        ]);

        OrderItem::factory()->create(['item_id' => $item->id, 'lens_power_id' => $lowPower->id, 'pieces' => 20, 'price' => 1000]);

        $this->assertEquals(
            ['0.50' => 100, '1.00' => 60],
            $item->quantitiesByPower()->pluck('remaining_pieces', 'power')->all(),
        );
    }

    /**
     * EN: Given one item bought in packets of 24 pieces:
     *     on a confirmed invoice, a line of 10 packets of power 1.00 whose packet holds 10 red pieces and 14 blue (100 red and 140 blue);
     *     on another confirmed invoice, a line of 2 packets of power 1.50 whose packet holds 24 red pieces (48 red);
     *     on an invoice only the importer confirmed, 5 packets of power 1.00 whose packet holds 24 red pieces (120 red on the way);
     *     on a confirmed invoice that was deleted, 1 packet of power 1.00 of 24 red pieces.
     *     2 packets of power 1.00 of 10 red and 14 blue are sold, so 20 red pieces and 28 blue of 1.00 go out with them.
     *     Then, power by power and color by color: 1.00 blue arrived 140 and has 112 left; 1.00 red arrived 100, has 120 on the way,
     *     and has 80 left; 1.50 red arrived 48 and has all 48 left, because the sale took only from its own power.
     *     The deleted invoice is not counted.
     * AR: مادة وحدة انشترت بباكيتات 24 قطعة:
     *     بفاتورة مؤكدة، بند بيه 10 باكيتات درجة 1.00 كل باكيت بيه 10 قطع أحمر و14 أزرق (100 أحمر و140 أزرق)؛
     *     وبفاتورة مؤكدة ثانية، بند بيه 2 باكيت درجة 1.50 كل باكيت بيه 24 قطعة أحمر (48 أحمر)؛
     *     وبفاتورة أكدها المستورد بس، 5 باكيتات درجة 1.00 كل باكيت بيه 24 قطعة أحمر (120 أحمر بالطريق)؛
     *     وبفاتورة مؤكدة انحذفت، باكيت واحد درجة 1.00 بيه 24 قطعة أحمر.
     *     انباع 2 باكيت درجة 1.00 بيهن 10 أحمر و14 أزرق، فطلعت وياهن 20 قطعة أحمر و28 أزرق من 1.00.
     *     فدرجة درجة ولون لون: 1.00 أزرق وصل 140 وباقي 112؛ 1.00 أحمر وصل 100، وبالطريق 120،
     *     وباقي 80؛ 1.50 أحمر وصل 48 وباقي كل الـ 48، لأن البيعة أخذت من درجتها بس.
     *     الفاتورة المحذوفة ما تنحسب.
     */
    public function test_the_item_knows_what_came_in_of_each_color(): void
    {
        $item = Item::factory()->create();
        $lowPower = LensPower::factory()->create(['value' => 1]);
        $highPower = LensPower::factory()->create(['value' => 1.5]);
        $red = Color::factory()->create(['name' => 'Red']);
        $blue = Color::factory()->create(['name' => 'Blue']);

        $redAndBlue = [['color_id' => $red->id, 'pieces' => 10], ['color_id' => $blue->id, 'pieces' => 14]];
        $allRed = [['color_id' => $red->id, 'pieces' => 24]];
        $buy = function (Invoice $invoice, LensPower $power, float $packets, array $colors) use ($item): void {
            $line = $this->purchaseLine($invoice, $item, ['boxes_count' => $packets, 'pieces_per_packet' => 24]);
            $line->colors()->createMany($colors);
            $line->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => $packets]);
        };

        $buy(Invoice::factory()->purchase()->confirmed()->create(), $lowPower, 10, $redAndBlue);
        $buy(Invoice::factory()->purchase()->confirmed()->create(), $highPower, 2, $allRed);
        $buy(Invoice::factory()->purchase()->importerConfirmed()->create(), $lowPower, 5, $allRed);

        $deleted = Invoice::factory()->purchase()->confirmed()->create();
        $buy($deleted, $lowPower, 1, $allRed);
        $deleted->delete();

        OrderItem::factory()->create([
            'item_id' => $item->id,
            'lens_power_id' => $lowPower->id,
            'colors' => [['color_id' => $red->id, 'pieces' => 10], ['color_id' => $blue->id, 'pieces' => 14]],
            'pieces' => 48,
            'price' => 1000,
        ]);

        $this->assertEquals([
            ['power' => '1.00', 'color' => 'Blue', 'arrived_pieces' => 140, 'incoming_pieces' => 0, 'remaining_pieces' => 112],
            ['power' => '1.00', 'color' => 'Red', 'arrived_pieces' => 100, 'incoming_pieces' => 120, 'remaining_pieces' => 80],
            ['power' => '1.50', 'color' => 'Red', 'arrived_pieces' => 48, 'incoming_pieces' => 0, 'remaining_pieces' => 48],
        ], $item->quantitiesByColor()->all());
    }

    /**
     * EN: Creates a purchase line for the item: one packet of 120 pieces (10 dozens) for 1000, so 100 a dozen, unless other values are given.
     * AR: ينشئ بند شراء للمادة: باكيت واحد بيه 120 قطعة (10 درازن) بسعر 1000، يعني الدرزن بـ 100، إلا إذا انطت قيم ثانية.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function purchaseLine(Invoice $invoice, Item $item, array $attributes = []): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 120,
            'price' => 1000,
            ...$attributes,
        ]);
    }
}
