<?php

namespace Tests\Unit;

use App\Enums\Package;
use App\Models\Color;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: An item's total price is its price of a dozen times its dozens, calculated on save; a given total price is ignored.
     *     3 dozens at 1500 a dozen is 4500.
     * AR: السعر الكلي للبند = سعر الدرزن × عدد الدرازن، ينحسب وقت الحفظ، وأي سعر كلي ينطى يدوياً ينهمل.
     *     3 درازن، الدرزن بـ 1500، يعني 4500.
     */
    public function test_total_price_is_price_times_quantity(): void
    {
        $line = OrderItem::factory()->create([
            'quantity' => 3,
            'price' => 1500,
            'total_price' => 99,
        ])->fresh();

        $this->assertEquals(4500, $line->total_price);
    }

    /**
     * EN: Given an item whose packet holds 20 pieces, a line is whole packets of it: 30 pieces is refused on the quantity
     *     and nothing is saved, while 40 pieces (2 packets) is sold.
     * AR: مادة الباكيت مالها بيه 20 قطعة، فالبند يكون باكيتات كاملة منها: 30 قطعة تنرفض على الكمية
     *     وما ينحفظ شي، و40 قطعة (2 باكيت) تنباع.
     */
    public function test_a_line_is_whole_packets_of_its_item(): void
    {
        $item = Item::factory()->stocked(10)->create(['pieces_per_packet' => 20]);

        try {
            OrderItem::factory()->create(['item_id' => $item->id, 'pieces' => 30, 'price' => 1000]);
            $this->fail('A line of a part of a packet was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertDatabaseCount('order_items', 0);

        OrderItem::factory()->create(['item_id' => $item->id, 'pieces' => 40, 'price' => 1000]);

        $this->assertDatabaseCount('order_items', 1);
    }

    /**
     * EN: Given an item whose packet holds 20 pieces at 15,000 a dozen, 7 packets are sold: 7 × 20 = 140 pieces,
     *     which is 11.67 dozens, so the line is 140 ÷ 12 × 15,000 = 175,000.
     * AR: مادة باكيتها 20 قطعة، والدرزن بـ 15,000، وانباع منها 7 باكيت: 7 × 20 = 140 قطعة،
     *     يعني 11.67 درزن، فالبند 140 ÷ 12 × 15,000 = 175,000.
     */
    public function test_packets_are_priced_by_their_dozens(): void
    {
        $item = Item::factory()->stocked(100)->create(['pieces_per_packet' => 20]);

        $line = OrderItem::factory()->create(['item_id' => $item->id, 'pieces' => 140, 'price' => 15000])->fresh();

        $this->assertEquals(140, $line->pieces);
        $this->assertEqualsWithDelta(140 / 12, $line->quantity, 0.0001);
        $this->assertEqualsWithDelta(175000, $line->total_price, 0.0001);
    }

    /**
     * EN: Given an item whose packet holds 24 pieces, 2 packets (48 pieces) of it are sold.
     *     Then the line keeps a packet of 24 pieces, even after the item's packet is changed to 20 pieces.
     * AR: مادة الباكيت مالها بيه 24 قطعة، وانباع منها 2 باكيت (48 قطعة).
     *     فالبند يحفظ إن باكيته 24 قطعة، حتى لو باكيت المادة تغيّر بعدين لـ 20 قطعة.
     */
    public function test_a_packet_line_keeps_the_packet_size_it_was_sold_at(): void
    {
        $item = Item::factory()->stocked(100)->create(['pieces_per_packet' => 24]);

        $packetLine = OrderItem::factory()->create(['item_id' => $item->id, 'pieces' => 48, 'package' => Package::Packet, 'price' => 1000]);

        $item->update(['pieces_per_packet' => 20]);

        $this->assertEquals(24, $packetLine->fresh()->package_size);
    }

    /**
     * EN: Values that are not more than zero.
     * AR: قيم مو أكبر من صفر.
     *
     * @return array<string, array{float}>
     */
    public static function notPositiveValues(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    /**
     * EN: A sold line keeps the item's cost price of a dozen of the moment it was sold, and its cost and profit come from it:
     *     2 dozens bought at 700 and sold at 1000 cost 1400 and make 600.
     * AR: البند المبيع يحتفظ بسعر تكلفة الدرزن للمادة لحظة بيعها، وكلفته وربحه ينحسبون منه:
     *     2 درزن كلفة الدرزن 700 وانباع بـ 1000، فكلفتهن 1400 وربحهن 600.
     */
    public function test_sold_line_keeps_the_cost_price_of_the_moment_it_was_sold(): void
    {
        $item = Item::factory()->stocked(10, 700)->create();

        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 2, 'price' => 1000])->fresh();

        $this->assertEquals(700, $line->cost_price);
        $this->assertEquals(1400, $line->total_cost);
        $this->assertEquals(600, $line->profit);
    }

    /**
     * EN: A later purchase changes the item's cost but never the cost or the profit of a line already sold.
     * AR: الشراء اللاحق يغيّر كلفة المادة، بس ما يغيّر كلفة ولا ربح بند انباع من قبل.
     */
    public function test_a_later_purchase_does_not_change_a_sold_line(): void
    {
        $item = Item::factory()->stocked(10, 700)->create();
        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 2, 'price' => 1000]);

        $this->buyAgain($item, dozens: 10, dozenCost: 900);

        $this->assertEquals(900, $item->fresh()->cost_price);
        $this->assertEquals(700, $line->fresh()->cost_price);
        $this->assertEquals(600, $line->fresh()->profit);
    }

    /**
     * EN: Changing the quantity of a sold line recalculates its cost and its profit with the cost price it kept.
     * AR: تغيير كمية بند مبيع يعيد حساب كلفته وربحه بسعر التكلفة المحفوظ عليه.
     */
    public function test_changing_the_quantity_recalculates_the_cost_and_the_profit(): void
    {
        $item = Item::factory()->stocked(10, 700)->create();
        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 2, 'price' => 1000]);

        $line->update(['quantity' => 3]);
        $line = $line->fresh();

        $this->assertEquals(2100, $line->total_cost);
        $this->assertEquals(900, $line->profit);
    }

    /**
     * EN: Buys the given dozens of the item again, at the given cost of a dozen, on a new purchase invoice both sides confirmed,
     *     so its cost price changes.
     * AR: يشتري الدرازن المحددة من المادة مرة ثانية، بكلفة الدرزن المحددة، بفاتورة شراء مؤكدة من الطرفين، فتتغير كلفتها.
     */
    private function buyAgain(Item $item, float $dozens, float $dozenCost): void
    {
        InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => $dozens * Item::PIECES_IN_A_DOZEN,
            'price' => $dozens * $dozenCost,
        ]);
    }

    /**
     * EN: An item is not sold for more than its available quantity.
     * AR: المادة ما تنباع بأكثر من كميتها المتوفرة.
     */
    public function test_selling_more_than_the_available_quantity_is_refused(): void
    {
        $item = Item::factory()->stocked(10)->create();

        $this->expectException(ValidationException::class);

        OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 11, 'price' => 1000]);
    }

    /**
     * EN: Raising the quantity of a sold line counts the line's own quantity as available, and stops at what is left.
     * AR: زيادة كمية بند مبيع تحسب كمية البند نفسه متوفرة، وتوقف عند اللي باقي.
     */
    public function test_raising_the_quantity_of_a_sold_line_stops_at_what_is_left(): void
    {
        $item = Item::factory()->stocked(10)->create();
        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 4, 'price' => 1000]);

        $line->update(['quantity' => 10]);

        $this->assertEquals(0, $item->fresh()->available_quantity);

        $this->expectException(ValidationException::class);

        $line->update(['quantity' => 11]);
    }

    /**
     * EN: Given an item bought at two powers: 60 dozens of 1.00 and 40 dozens of 1.50, 100 in all.
     *     Then 50 dozens of 1.50 cannot be sold, although the item has 100, and all 40 dozens of 1.50 can.
     * AR: مادة انشترت بدرجتين: 60 درزن 1.00 و40 درزن 1.50، يعني 100 بالمجموع.
     *     فـ 50 درزن من 1.50 ما تنباع، حتى لو المادة بيها 100، وكل الـ 40 درزن مال 1.50 تنباع.
     */
    public function test_a_power_is_not_sold_for_more_than_is_left_of_it(): void
    {
        $item = Item::factory()->create();
        $this->buyAtPower($item, LensPower::factory()->create(['value' => 1]), 60);
        $higherPower = LensPower::factory()->create(['value' => 1.5]);
        $this->buyAtPower($item, $higherPower, 40);

        try {
            OrderItem::factory()->create(['item_id' => $item->id, 'lens_power_id' => $higherPower->id, 'quantity' => 50, 'price' => 1000]);
            $this->fail('Selling more than is left of a power must be refused.');
        } catch (ValidationException) {
            //
        }

        OrderItem::factory()->create(['item_id' => $item->id, 'lens_power_id' => $higherPower->id, 'quantity' => 40, 'price' => 1000]);

        $this->assertEquals(60, $item->fresh()->available_quantity);
    }

    /**
     * EN: An item bought only with powers is not sold without a power: the seller has to choose one, and the error is on the power.
     * AR: المادة اللي انشترت بدرجات بس ما تنباع بلا درجة: البائع لازم يختار وحدة، والخطأ يطلع على الدرجة.
     */
    public function test_an_item_bought_with_powers_is_not_sold_without_a_power(): void
    {
        $item = Item::factory()->create();
        $this->buyAtPower($item, LensPower::factory()->create(['value' => 1]), 60);

        try {
            OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 1, 'price' => 1000]);
            $this->fail('Selling an item bought with powers without a power must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lens_power_id', $exception->errors());
        }
    }

    /**
     * EN: Given an item whose packet holds 20 pieces, bought at power 1.00 in two mixes of colors:
     *     5 packets of 10 red and 10 blue, and 5 packets of 20 black.
     *     Then 6 packets of red and blue cannot be sold, although the power has 10 packets; 5 packets of red and blue can.
     *     After them none of red and blue is left, so not one more packet of it can be sold, while a packet of black still can.
     *     4 packets (80 pieces) of the item are left, which is 6.67 dozens.
     * AR: مادة الباكيت مالها بيه 20 قطعة، انشترت بدرجة 1.00 بتشكيلتين ألوان:
     *     5 باكيتات بيها 10 أحمر و10 أزرق، و5 باكيتات بيها 20 أسود.
     *     فـ 6 باكيتات أحمر وأزرق ما تنباع، حتى لو الدرجة بيها 10 باكيتات؛ و5 باكيتات أحمر وأزرق تنباع.
     *     وبعدها ما باقي شي من الأحمر والأزرق، فولا باكيت منه ينباع، بس باكيت أسود بعده ينباع.
     *     ويبقى من المادة 4 باكيتات (80 قطعة)، يعني 6.67 درزن.
     */
    public function test_a_packet_type_is_not_sold_for_more_than_is_left_of_it(): void
    {
        $item = Item::factory()->create(['pieces_per_packet' => 20]);
        $power = LensPower::factory()->create(['value' => 1]);
        $redAndBlue = [
            ['color_id' => Color::factory()->create(['name' => 'Red'])->id, 'pieces' => 10],
            ['color_id' => Color::factory()->create(['name' => 'Blue'])->id, 'pieces' => 10],
        ];
        $black = [['color_id' => Color::factory()->create(['name' => 'Black'])->id, 'pieces' => 20]];
        $this->buyPackets($item, $power, packets: 5, colors: $redAndBlue);
        $this->buyPackets($item, $power, packets: 5, colors: $black);
        $sell = fn (array $colors, float $packets): OrderItem => OrderItem::factory()->create([
            'item_id' => $item->id,
            'lens_power_id' => $power->id,
            'colors' => $colors,
            'pieces' => $packets * 20,
            'price' => 1000,
        ]);

        $this->assertRefused(fn () => $sell($redAndBlue, 6), 'quantity');

        $sell($redAndBlue, 5);

        $this->assertRefused(fn () => $sell($redAndBlue, 1), 'quantity');

        $sell($black, 1);

        $this->assertEqualsWithDelta(80 / 12, $item->fresh()->available_quantity, 0.0001);
    }

    /**
     * EN: An item bought in mixes of colors is not sold without one: the seller has to choose the colors of its packets,
     *     and the error is on the colors.
     * AR: المادة اللي انشترت بتشكيلات ألوان ما تنباع بلا تشكيلة: البائع لازم يختار ألوان باكيتاتها، والخطأ يطلع على الألوان.
     */
    public function test_an_item_bought_in_color_mixes_is_not_sold_without_one(): void
    {
        $item = Item::factory()->create(['pieces_per_packet' => 20]);
        $power = LensPower::factory()->create(['value' => 1]);
        $this->buyPackets($item, $power, packets: 5, colors: [['color_id' => Color::factory()->create()->id, 'pieces' => 20]]);

        $this->assertRefused(fn () => OrderItem::factory()->create([
            'item_id' => $item->id,
            'lens_power_id' => $power->id,
            'pieces' => 20,
            'price' => 1000,
        ]), 'colors');
    }

    /**
     * EN: Runs the sale, and checks that it is refused with an error on the given field.
     * AR: يشغّل البيعة، ويتأكد إنها تنرفض والخطأ على الحقل المحدد.
     */
    private function assertRefused(callable $sale, string $field): void
    {
        try {
            $sale();
            $this->fail('The sale must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    /**
     * EN: Buys the given packets of the item at the given power as one line, each packet holding the given pieces of each color,
     *     on a purchase invoice both sides confirmed, at 100 a packet.
     * AR: يشتري الباكيتات المحددة من المادة بالدرجة المحددة كبند واحد، كل باكيت بيه القطع المحددة من كل لون،
     *     بفاتورة شراء مؤكدة من الطرفين، الباكيت بـ 100.
     *
     * @param  list<array{color_id: int, pieces: float}>  $colors
     */
    private function buyPackets(Item $item, LensPower $power, float $packets, array $colors): void
    {
        $line = InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => $packets,
            'pieces_per_packet' => $item->pieces_per_packet,
            'price' => $packets * 100,
        ]);
        $line->colors()->createMany($colors);
        $line->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => $packets]);
    }

    /**
     * EN: Buys the given dozens of the item at the given power, as one packet, on a purchase invoice both sides confirmed, at 100 a dozen.
     * AR: يشتري الدرازن المحددة من المادة بالدرجة المحددة، بباكيت واحد، بفاتورة شراء مؤكدة من الطرفين، الدرزن بـ 100.
     */
    private function buyAtPower(Item $item, LensPower $power, float $dozens): void
    {
        InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => $dozens * Item::PIECES_IN_A_DOZEN,
            'price' => $dozens * 100,
        ])->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => 1]);
    }

    /**
     * EN: An item's quantity must be more than zero.
     * AR: كمية البند لازم تكون أكبر من صفر.
     */
    #[DataProvider('notPositiveValues')]
    public function test_quantity_must_be_more_than_zero(float $quantity): void
    {
        $this->expectException(ValidationException::class);

        OrderItem::factory()->create(['quantity' => $quantity, 'price' => 1000]);
    }

    /**
     * EN: An item's price must be more than zero.
     * AR: سعر البند لازم يكون أكبر من صفر.
     */
    #[DataProvider('notPositiveValues')]
    public function test_price_must_be_more_than_zero(float $price): void
    {
        $this->expectException(ValidationException::class);

        OrderItem::factory()->create(['quantity' => 1, 'price' => $price]);
    }
}
