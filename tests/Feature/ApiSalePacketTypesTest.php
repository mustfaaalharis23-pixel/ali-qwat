<?php

namespace Tests\Feature;

use App\Models\Color;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSalePacketTypesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given reading glasses in packets of 20 pieces: at power 1.00, 5 packets of 10 red and 10 blue, then 5 packets of 20 black;
     *     at power 1.50, 3 packets of 10 red and 10 blue. 2 packets of 1.00 of 10 red and 10 blue were sold.
     *     And plain glasses in packets of 24 pieces, bought without a power and without colors: 10 dozens, so 5 packets.
     *     Then the items list shows what the seller can choose from: each power with each mix of colors in its packets,
     *     the packets left of it and their dozens, from the lowest power, the oldest mix first:
     *     1.00 red and blue has 3 packets (5 dozens), 1.00 black has 5 packets (8.33 dozens), and 1.50 red and blue has 3 packets (5 dozens).
     *     The plain glasses have one type without a power and without colors, of 5 packets (10 dozens).
     * AR: نظارة قراءة باكيتها 20 قطعة: بدرجة 1.00، 5 باكيتات بيها 10 أحمر و10 أزرق، وبعدها 5 باكيتات بيها 20 أسود؛
     *     وبدرجة 1.50، 3 باكيتات بيها 10 أحمر و10 أزرق. وانباع 2 باكيت من 1.00 مال 10 أحمر و10 أزرق.
     *     ونظارة عادية باكيتها 24 قطعة، انشترت بلا درجة وبلا ألوان: 10 درازن، يعني 5 باكيتات.
     *     فقائمة المواد تبيّن شنو يگدر البائع يختار: كل درجة ويا كل تشكيلة ألوان بباكيتاتها،
     *     والباكيتات الباقية منها ودرازنها، من الدرجة الأصغر، والتشكيلة الأقدم أول:
     *     1.00 أحمر وأزرق باقي 3 باكيتات (5 درازن)، و1.00 أسود باقي 5 باكيتات (8.33 درزن)، و1.50 أحمر وأزرق باقي 3 باكيتات (5 درازن).
     *     والنظارة العادية إلها نوع واحد بلا درجة وبلا ألوان، بيه 5 باكيتات (10 درازن).
     */
    public function test_items_list_shows_the_packet_types_left(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));
        $glasses = Item::factory()->create(['name' => 'Reading glasses', 'sale_price' => 1000, 'pieces_per_packet' => 20]);
        $lowPower = LensPower::factory()->create(['value' => 1]);
        $highPower = LensPower::factory()->create(['value' => 1.5]);
        $red = Color::factory()->create(['name' => 'Red']);
        $blue = Color::factory()->create(['name' => 'Blue']);
        $black = Color::factory()->create(['name' => 'Black']);
        $redAndBlue = [['color_id' => $red->id, 'pieces' => 10], ['color_id' => $blue->id, 'pieces' => 10]];
        $this->buy($glasses, $lowPower, packets: 5, colors: $redAndBlue);
        $this->buy($glasses, $lowPower, packets: 5, colors: [['color_id' => $black->id, 'pieces' => 20]]);
        $this->buy($glasses, $highPower, packets: 3, colors: $redAndBlue);
        OrderItem::factory()->create([
            'item_id' => $glasses->id,
            'lens_power_id' => $lowPower->id,
            'colors' => $redAndBlue,
            'pieces' => 40,
            'price' => 1000,
        ]);
        Item::factory()->stocked(10)->create(['name' => 'Plain glasses', 'pieces_per_packet' => 24]);

        $redAndBlueAsListed = [
            ['id' => $red->id, 'name' => 'Red', 'pieces' => 10],
            ['id' => $blue->id, 'name' => 'Blue', 'pieces' => 10],
        ];

        $this->getJson('/api/v1/items?search=Reading')
            ->assertOk()
            ->assertJsonPath('data.0.packet_types', [
                ['lens_power_id' => $lowPower->id, 'colors' => $redAndBlueAsListed, 'available_packets' => 3, 'available_quantity' => 5],
                ['lens_power_id' => $lowPower->id, 'colors' => [['id' => $black->id, 'name' => 'Black', 'pieces' => 20]], 'available_packets' => 5, 'available_quantity' => 8.33],
                ['lens_power_id' => $highPower->id, 'colors' => $redAndBlueAsListed, 'available_packets' => 3, 'available_quantity' => 5],
            ]);

        $this->getJson('/api/v1/items?search=Plain')
            ->assertOk()
            ->assertJsonPath('data.0.packet_types', [
                ['lens_power_id' => null, 'colors' => [], 'available_packets' => 5, 'available_quantity' => 10],
            ]);
    }

    /**
     * EN: A sale line of 2 packets sent with a power and its packets' colors keeps them, and the order's details show the colors
     *     of one packet as [{ id, name, pieces }].
     * AR: بند بيع بيه 2 باكيت ينرسل ويا درجة وألوان باكيتاته يحفظها، وتفاصيل الأوردر تطلع ألوان الباكيت الواحد
     *     بشكل [{ id, name, pieces }].
     */
    public function test_a_sale_line_keeps_its_packets_colors_and_the_order_shows_them(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));
        $item = Item::factory()->create(['sale_price' => 1000, 'pieces_per_packet' => 20]);
        $power = LensPower::factory()->create(['value' => 1.5]);
        $red = Color::factory()->create(['name' => 'Red']);
        $blue = Color::factory()->create(['name' => 'Blue']);
        $colors = [['color_id' => $red->id, 'pieces' => 10], ['color_id' => $blue->id, 'pieces' => 10]];
        $this->buy($item, $power, packets: 5, colors: $colors);

        $orderId = $this->postJson('/api/v1/orders', [
            'customer_id' => Customer::factory()->create()->id,
            'payment_type' => 'cash',
            'lines' => [
                ['item_id' => $item->id, 'packets' => 2, 'lens_power_id' => $power->id, 'colors' => $colors],
            ],
        ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.packets', 2)
            ->assertJsonPath('data.items.0.colors', [
                ['id' => $red->id, 'name' => 'Red', 'pieces' => 10],
                ['id' => $blue->id, 'name' => 'Blue', 'pieces' => 10],
            ]);
    }

    /**
     * EN: A sale line of an item bought in color mixes, sent without its packets' colors, is refused with an error on the colors,
     *     and no order is saved.
     * AR: بند بيع لمادة انشترت بتشكيلات ألوان، ينرسل بلا ألوان باكيتاته، ينرفض والخطأ على الألوان، وما ينحفظ أوردر.
     */
    public function test_a_sale_without_colors_of_an_item_bought_in_color_mixes_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));
        $item = Item::factory()->create(['sale_price' => 1000, 'pieces_per_packet' => 20]);
        $power = LensPower::factory()->create(['value' => 1.5]);
        $this->buy($item, $power, packets: 5, colors: [['color_id' => Color::factory()->create()->id, 'pieces' => 20]]);

        $this->postJson('/api/v1/orders', [
            'customer_id' => Customer::factory()->create()->id,
            'payment_type' => 'cash',
            'lines' => [
                ['item_id' => $item->id, 'packets' => 1, 'lens_power_id' => $power->id],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['colors']);

        $this->assertSame(0, OrderItem::query()->count());
    }

    /**
     * EN: Buys the given packets of the item at the given power as one line, each packet holding the given pieces of each color,
     *     on a purchase invoice both sides confirmed, at 100 a packet.
     * AR: يشتري الباكيتات المحددة من المادة بالدرجة المحددة كبند واحد، كل باكيت بيه القطع المحددة من كل لون،
     *     بفاتورة شراء مؤكدة من الطرفين، الباكيت بـ 100.
     *
     * @param  list<array{color_id: int, pieces: float}>  $colors
     */
    private function buy(Item $item, LensPower $power, float $packets, array $colors): void
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
}
