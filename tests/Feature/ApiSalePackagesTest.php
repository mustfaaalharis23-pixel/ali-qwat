<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiSalePackagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The application gets the packages list from the server: only the packet, because an item is sold by the whole packet only,
     *     with its label in the request's language. The packet has no size in the list, because each item has its own: 20 or 24 pieces.
     * AR: التطبيق ياخذ قائمة التعبئات من السيرفر: الباكيت بس، لأن المادة تنباع بالباكيت الكامل بس،
     *     واسمه بلغة الطلب. الباكيت ما إله حجم بالقائمة، لأن كل مادة إلها حجمها: 20 أو 24 قطعة.
     */
    public function test_application_gets_the_packages_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/packages')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['value' => 'packet', 'label' => 'باكيت', 'size' => null],
                ],
            ]);
    }

    /**
     * EN: A user without the checkout permission cannot get the packages list: 403.
     * AR: المستخدم اللي ما عنده صلاحية البيع ما ياخذ قائمة التعبئات: 403.
     */
    public function test_packages_list_needs_the_checkout_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/packages')->assertForbidden();
    }

    /**
     * EN: The items list gives each item's pieces per packet (24), or null for an item without a packet size,
     *     and what is in stock in dozens: 100 dozens bought is 100.
     * AR: قائمة المواد تعطي قطع الباكيت لكل مادة (24)، أو null للمادة اللي ما إلها حجم باكيت،
     *     والمتوفر بالدرزن: 100 درزن انشترت تطلع 100.
     */
    public function test_items_list_gives_the_pieces_per_packet(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));
        Item::factory()->stocked(100)->create(['name' => 'Reading glasses', 'pieces_per_packet' => 24]);
        Item::factory()->stocked(100)->create(['name' => 'Sunglasses']);

        $this->getJson('/api/v1/items?search=Reading')
            ->assertOk()
            ->assertJsonPath('data.0.pieces_per_packet', 24)
            ->assertJsonPath('data.0.available_quantity', 100);

        $this->getJson('/api/v1/items?search=Sunglasses')
            ->assertOk()
            ->assertJsonPath('data.0.pieces_per_packet', null);
    }

    /**
     * EN: Given an item of 20 pieces per packet at 15,000 a dozen, 7 packets are sold: 7 × 20 = 140 pieces, which is 11.67 dozens,
     *     so the line is 140 ÷ 12 × 15,000 = 175,000. The order's details show the packets, the pieces, the dozens, and the packet of 20.
     * AR: مادة باكيتها 20 قطعة، والدرزن بـ 15,000، وانباع منها 7 باكيت: 7 × 20 = 140 قطعة، يعني 11.67 درزن،
     *     فالبند 140 ÷ 12 × 15,000 = 175,000. وتفاصيل الأوردر تطلع الباكيتات والقطع والدرازن والباكيت مال 20.
     */
    public function test_a_sale_is_whole_packets_priced_by_the_dozen(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $orderId = $this->postJson('/api/v1/orders', $this->sale(['packets' => 7]))
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('Accept-Language', 'ar')
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.amount', 175000)
            ->assertJsonPath('data.items.0.packets', 7)
            ->assertJsonPath('data.items.0.pieces', 140)
            ->assertJsonPath('data.items.0.quantity', 11.67)
            ->assertJsonPath('data.items.0.total_price', 175000)
            ->assertJsonPath('data.items.0.package', ['value' => 'packet', 'label' => 'باكيت', 'size' => 20]);
    }

    /**
     * EN: The packets that cannot be sold: a fraction of a packet (2.5), none, or less than none.
     * AR: الباكيتات اللي ما تنباع: جزء من باكيت (2.5)، أو صفر، أو أقل من صفر.
     *
     * @return array<string, array{float}>
     */
    public static function packetsThatCannotBeSold(): array
    {
        return [
            'a fraction of a packet' => [2.5],
            'no packets' => [0],
            'less than no packets' => [-1],
        ];
    }

    /**
     * EN: Such packets come back as 422 on the line's packets, and no order is saved.
     * AR: هيج باكيتات ترجع 422 على باكيتات البند، وما ينحفظ أوردر.
     */
    #[DataProvider('packetsThatCannotBeSold')]
    public function test_packets_that_cannot_be_sold_are_rejected(float $packets): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $this->postJson('/api/v1/orders', $this->sale(['packets' => $packets]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('lines.0.packets');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * EN: An item without a packet size cannot be sold, because a sale is whole packets: 422 on the line's packets, and no order is saved.
     * AR: المادة اللي ما إلها حجم باكيت ما تنباع، لأن البيع باكيتات كاملة: 422 على باكيتات البند، وما ينحفظ أوردر.
     */
    public function test_item_without_a_packet_size_is_not_sold(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $this->postJson('/api/v1/orders', $this->sale(['packets' => 1], piecesPerPacket: null))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('lines.0.packets');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * EN: A valid cash sale body of one stocked item priced 15,000 a dozen, with 20 pieces per packet unless another size is given,
     *     and the given line fields.
     * AR: جسم بيعة كاش صحيح ببند واحد لمادة عندها مخزون، الدرزن بيها بـ 15,000، وباكيتها 20 قطعة إلا إذا انطى حجم ثاني،
     *     بحقول البند المحددة.
     *
     * @param  array{packets: float|int}  $line
     * @return array<string, mixed>
     */
    private function sale(array $line, ?int $piecesPerPacket = 20): array
    {
        $item = Item::factory()->stocked(dozenCost: 1000)->create(['sale_price' => 15000, 'pieces_per_packet' => $piecesPerPacket]);

        return [
            'customer_id' => Customer::factory()->create()->id,
            'payment_type' => 'cash',
            'lines' => [
                ['item_id' => $item->id, ...$line],
            ],
        ];
    }
}
