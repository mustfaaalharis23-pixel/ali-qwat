<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutWeeklyPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A weekly payment equal to what is left after the down payment (1000 - 300 = 700) is accepted and kept on the order.
     * AR: الدفعة الأسبوعية اللي تساوي الباقي بعد الدفعة الأولية (1000 - 300 = 700) تنقبل وتنحفظ على الأوردر.
     */
    public function test_weekly_payment_up_to_what_is_left_after_the_down_payment_is_kept_on_the_order(): void
    {
        $order = (new Checkout)->handle(User::factory()->create(), $this->sale(total: 1000, downPayment: 300, weeklyPayment: 700))->fresh();

        $this->assertEquals(700, $order->weekly_payment);
        $this->assertEquals(700, $order->remaining);
    }

    /**
     * EN: Weekly payments that cannot be taken on a postpaid order of 1000: more than the order, more than what is left
     *     after the down payment, zero, and negative.
     * AR: دفعات أسبوعية ما تنقبل على أوردر آجل بـ 1000: أكثر من الأوردر، أكثر من الباقي بعد الدفعة الأولية، صفر، وسالب.
     *
     * @return array<string, array{?float, float}>
     */
    public static function weeklyPaymentsThatCannotBeTaken(): array
    {
        return [
            'more than the order' => [null, 1001],
            'more than what is left after the down payment' => [300, 701],
            'zero' => [null, 0],
            'negative' => [null, -50],
        ];
    }

    /**
     * EN: A weekly payment that cannot be taken rejects the whole sale on the weekly payment field, and nothing is saved.
     * AR: الدفعة الأسبوعية اللي ما تنقبل ترفض البيعة كلها على حقل الدفعة الأسبوعية، وما ينحفظ شي.
     */
    #[DataProvider('weeklyPaymentsThatCannotBeTaken')]
    public function test_weekly_payment_that_cannot_be_taken_rejects_the_whole_sale(?float $downPayment, float $weeklyPayment): void
    {
        $this->assertSaleIsRejected($this->sale(total: 1000, downPayment: $downPayment, weeklyPayment: $weeklyPayment));
    }

    /**
     * EN: A weekly payment is for postpaid sales only: a cash sale with a weekly payment is rejected, and nothing is saved.
     * AR: الدفعة الأسبوعية للبيع الآجل بس: البيعة الكاش ويا دفعة أسبوعية تنرفض، وما ينحفظ شي.
     */
    public function test_cash_sale_with_a_weekly_payment_is_rejected(): void
    {
        $this->assertSaleIsRejected($this->sale(total: 1000, weeklyPayment: 100, paymentType: OrderPaymentType::Cash));
    }

    /**
     * EN: Asserts that the checkout throws a validation error on the weekly payment field and saves no order, no item, no payment, and no invoice.
     * AR: يتأكد إن الچيك اوت يرمي خطأ تحقق على حقل الدفعة الأسبوعية وما يحفظ لا أوردر ولا بند ولا تسديد ولا فاتورة.
     */
    private function assertSaleIsRejected(CheckoutData $data): void
    {
        try {
            (new Checkout)->handle(User::factory()->create(), $data);

            $this->fail('The sale was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('weekly_payment', $exception->errors());
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
            $this->assertDatabaseCount('customer_payments', 0);
            $this->assertSame(0, Invoice::query()->revenues()->count());
        }
    }

    /**
     * EN: Builds a sale of one line worth the given total for a new customer, postpaid unless another payment type is given.
     * AR: يبني بيعة ببند واحد قيمته المجموع المحدد لزبون جديد، آجلة إلا إذا انطت طريقة دفع ثانية.
     */
    private function sale(
        float $total = 1000,
        ?float $downPayment = null,
        ?float $weeklyPayment = null,
        OrderPaymentType $paymentType = OrderPaymentType::Postpaid,
    ): CheckoutData {
        $item = Item::factory()->stocked()->create(['sale_price' => $total]);

        return new CheckoutData(
            customerId: Customer::factory()->create()->id,
            paymentType: $paymentType,
            date: '2026-10-23',
            lines: [new CheckoutLine($item->id, quantity: 1)],
            downPayment: $downPayment,
            weeklyPayment: $weeklyPayment,
        );
    }
}
