<?php

namespace Tests\Feature;

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

class ApiSalePowersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given reading glasses in packets of 24 pieces bought at 1.00 (60 dozens), 1.50 (40 dozens, 10 sold), and 2.00 (4 dozens, all sold),
     *     and sunglasses bought without a power.
     *     Then the items list shows what is left of each power of the glasses in dozens, from the lowest: 1.00 has 60 and 1.50 has 30,
     *     and 2.00 is not listed because nothing is left of it. The sunglasses have an empty list, and are sold as before.
     * AR: نظارة قراءة باكيتها 24 قطعة انشترت بـ 1.00 (60 درزن)، و1.50 (40 درزن، انباع منها 10)، و2.00 (4 درازن، انباعن كلهن)،
     *     ونظارة شمسية انشترت بلا درجة.
     *     فقائمة المواد تبيّن الباقي من كل درجة للنظارة بالدرزن، من الأصغر: 1.00 بيها 60 و1.50 بيها 30،
     *     و2.00 ما تطلع لأن ما باقي منها شي. والشمسية قائمتها فارغة، وتنباع مثل قبل.
     */
    public function test_items_list_shows_what_is_left_of_each_power(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));
        $glasses = Item::factory()->create(['name' => 'Reading glasses', 'sale_price' => 1000, 'pieces_per_packet' => 24]);
        $lowPower = LensPower::factory()->create(['value' => 1]);
        $middlePower = LensPower::factory()->create(['value' => 1.5]);
        $soldOutPower = LensPower::factory()->create(['value' => 2]);
        $this->buyAtPower($glasses, $lowPower, 60);
        $this->buyAtPower($glasses, $middlePower, 40);
        $this->buyAtPower($glasses, $soldOutPower, 4);
        OrderItem::factory()->create(['item_id' => $glasses->id, 'lens_power_id' => $middlePower->id, 'quantity' => 10, 'price' => 1000]);
        OrderItem::factory()->create(['item_id' => $glasses->id, 'lens_power_id' => $soldOutPower->id, 'quantity' => 4, 'price' => 1000]);
        Item::factory()->stocked(10)->create(['name' => 'Sunglasses']);

        $this->getJson('/api/v1/items?search=Reading')
            ->assertOk()
            ->assertJsonPath('data.0.powers', [
                ['id' => $lowPower->id, 'value' => '1.00', 'available_quantity' => 60],
                ['id' => $middlePower->id, 'value' => '1.50', 'available_quantity' => 30],
            ]);

        $this->getJson('/api/v1/items?search=Sunglasses')
            ->assertOk()
            ->assertJsonPath('data.0.powers', []);
    }

    /**
     * EN: A sale line of 6 packets sent with a power keeps it, and the order's details show it as { id, value }.
     * AR: بند بيع بيه 6 باكيتات ينرسل وياه درجة يحفظها، وتفاصيل الأوردر تطلعها بشكل { id, value }.
     */
    public function test_a_sale_line_keeps_its_power_and_the_order_shows_it(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));
        $item = Item::factory()->create(['sale_price' => 1000, 'pieces_per_packet' => 24]);
        $power = LensPower::factory()->create(['value' => 1.5]);
        $this->buyAtPower($item, $power, 40);

        $orderId = $this->postJson('/api/v1/orders', [
            'customer_id' => Customer::factory()->create()->id,
            'payment_type' => 'cash',
            'lines' => [
                ['item_id' => $item->id, 'packets' => 6, 'lens_power_id' => $power->id],
            ],
        ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.lens_power', ['id' => $power->id, 'value' => '1.50']);
    }

    /**
     * EN: Buys the given dozens of the item at the given power in its packets of 24 pieces, on a purchase invoice both sides confirmed,
     *     at 100 a dozen.
     * AR: يشتري الدرازن المحددة من المادة بالدرجة المحددة بباكيتاتها اللي بيها 24 قطعة، بفاتورة شراء مؤكدة من الطرفين،
     *     الدرزن بـ 100.
     */
    private function buyAtPower(Item $item, LensPower $power, float $dozens): void
    {
        $packets = $dozens * Item::PIECES_IN_A_DOZEN / 24;

        InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => $packets,
            'pieces_per_packet' => 24,
            'price' => $dozens * 100,
        ])->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => $packets]);
    }
}
