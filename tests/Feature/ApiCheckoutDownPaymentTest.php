<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiCheckoutDownPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The application sends the down payment with a postpaid sale, and the saved order keeps it.
     * AR: التطبيق يدز الدفعة الأولية ويا البيعة الآجلة، والأوردر المحفوظ يحتفظ بيها.
     */
    public function test_application_sends_a_down_payment_with_a_postpaid_sale(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $response = $this->postJson('/api/v1/orders', $this->sale(paymentType: 'postpaid', downPayment: 300));

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['id' => $response->json('data.id'), 'down_payment' => 300]);
    }

    /**
     * EN: A down payment with a cash sale comes back as 422 on down_payment, and no order is saved.
     * AR: الدفعة الأولية ويا بيعة كاش ترجع 422 على down_payment، وما ينحفظ أوردر.
     */
    public function test_down_payment_with_a_cash_sale_is_rejected_with_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super' => true]));

        $this->postJson('/api/v1/orders', $this->sale(paymentType: 'cash', downPayment: 300))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('down_payment');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * EN: A valid sale body of one packet of a stocked item (24 pieces, 2 dozens at 1000 a dozen, so worth 2000),
     *     with the given payment type and down payment.
     * AR: جسم بيعة صحيح بباكيت واحد لمادة عندها مخزون (24 قطعة، يعني 2 درزن، الدرزن بـ 1000، فقيمته 2000)،
     *     بطريقة الدفع والدفعة الأولية المحددة.
     *
     * @return array<string, mixed>
     */
    private function sale(string $paymentType, float $downPayment): array
    {
        return [
            'customer_id' => Customer::factory()->create()->id,
            'payment_type' => $paymentType,
            'down_payment' => $downPayment,
            'lines' => [
                ['item_id' => Item::factory()->stocked()->create(['sale_price' => 1000, 'pieces_per_packet' => 24])->id, 'packets' => 1],
            ],
        ];
    }
}
