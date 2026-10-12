<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSalePackagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The application gets the packages list from the server: piece 1, dozen 12, packet 20, in that order, with labels in the request's language.
     * AR: التطبيق ياخذ قائمة التعبئات من السيرفر: قطعة 1، درزن 12، باكيت 20، بهذا الترتيب، وأسماءها بلغة الطلب.
     */
    public function test_application_gets_the_packages_list(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/packages')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['value' => 'piece', 'label' => 'قطعة', 'size' => 1],
                    ['value' => 'dozen', 'label' => 'درزن', 'size' => 12],
                    ['value' => 'packet', 'label' => 'باكيت', 'size' => 20],
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
     * EN: A line sold by the dozen keeps its quantity in pieces (24) and its price from the pieces; the package is only remembered and shown in the order's details.
     * AR: البند اللي ينباع بالدرزن تضل كميته بالقطع (24) وسعره على القطع، والتعبئة بس تنحفظ وتطلع بتفاصيل الأوردر.
     */
    public function test_line_sold_by_package_keeps_the_pieces_and_shows_the_package(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $orderId = $this->postJson('/api/v1/orders', $this->sale(['quantity' => 24, 'package' => 'dozen']))
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('Accept-Language', 'ar')
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.amount', 24000)
            ->assertJsonPath('data.items.0.quantity', 24)
            ->assertJsonPath('data.items.0.total_price', 24000)
            ->assertJsonPath('data.items.0.package', ['value' => 'dozen', 'label' => 'درزن']);
    }

    /**
     * EN: A line sent without a package is saved as a piece.
     * AR: البند اللي ينرسل بلا تعبئة ينحفظ قطعة.
     */
    public function test_line_without_a_package_is_a_piece(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $orderId = $this->postJson('/api/v1/orders', $this->sale(['quantity' => 5]))
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('Accept-Language', 'ar')
            ->getJson("/api/v1/orders/{$orderId}")
            ->assertJsonPath('data.items.0.package', ['value' => 'piece', 'label' => 'قطعة']);
    }

    /**
     * EN: A quantity that is not whole packages (25 with a dozen) comes back as 422 on the line's quantity, and no order is saved.
     * AR: الكمية اللي ما تصير تعبئات كاملة (25 ويا درزن) ترجع 422 على كمية البند، وما ينحفظ أوردر.
     */
    public function test_quantity_that_is_not_whole_packages_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $this->postJson('/api/v1/orders', $this->sale(['quantity' => 25, 'package' => 'dozen']))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('lines.0.quantity');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * EN: A valid cash sale body of one stocked item priced 1000 per piece, with the given line fields.
     * AR: جسم بيعة كاش صحيح ببند واحد لمادة عندها مخزون سعر القطعة بيها 1000، بحقول البند المحددة.
     *
     * @param  array{quantity: float|int, package?: string}  $line
     * @return array<string, mixed>
     */
    private function sale(array $line): array
    {
        return [
            'customer_id' => Customer::factory()->create()->id,
            'payment_type' => 'cash',
            'lines' => [
                ['item_id' => Item::factory()->stocked()->create(['sale_price' => 1000])->id, ...$line],
            ],
        ];
    }
}
