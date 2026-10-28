<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Metrics\Period;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use App\Orders\PayCustomerDebt;
use App\Orders\UpdateOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostpaidBoxMatchesCustomerDebtsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Postpaid orders that arrive through the checkout become their customers' debts, and the postpaid box holds exactly that money.
     *     A cash order goes to the cash box only and adds no debt.
     * AR: الأوردرات الآجلة اللي تجي عن طريق الـ checkout تصير دين على زبائنها، وصندوق الآجل يكون بيه نفس هذا المبلغ بالضبط.
     *     الأوردر الكاش يروح لصندوق الكاش بس وما يضيف دين.
     */
    public function test_postpaid_orders_become_debts_that_fill_the_postpaid_box(): void
    {
        $ahmed = Customer::factory()->create();
        $sara = Customer::factory()->create();

        $this->sell($ahmed, OrderPaymentType::Postpaid, 1000);
        $this->sell($ahmed, OrderPaymentType::Postpaid, 300);
        $this->sell($sara, OrderPaymentType::Postpaid, 500);
        $this->sell($sara, OrderPaymentType::Cash, 900);

        $this->assertEquals(1300, $ahmed->debt());
        $this->assertEquals(500, $sara->debt());
        $this->assertPostpaidBoxMatchesDebts(1800);
        $this->assertEquals(900, Order::boxBalance(OrderPaymentType::Cash, $this->allTime()));
    }

    /**
     * EN: Every payment takes the same amount from its customer's debt and from the postpaid box, so the two stay equal
     *     after each payment, when the debts are fully paid, and after a payment is deleted.
     * AR: كل تسديد ينقص نفس المبلغ من دين زبونه ومن صندوق الآجل، فالثنين يبقون متساويين
     *     بعد كل تسديد، ولمن الديون تنسدد كلها، وبعد حذف تسديد.
     */
    public function test_payments_keep_the_postpaid_box_equal_to_the_debts(): void
    {
        $ahmed = Customer::factory()->create();
        $sara = Customer::factory()->create();
        $this->sell($ahmed, OrderPaymentType::Postpaid, 1000);
        $this->sell($ahmed, OrderPaymentType::Postpaid, 300);
        $this->sell($sara, OrderPaymentType::Postpaid, 500);

        $this->pay($ahmed, 400);
        $this->assertPostpaidBoxMatchesDebts(1400);

        $this->pay($sara, 500);
        $this->assertPostpaidBoxMatchesDebts(900);

        $lastPayment = $this->pay($ahmed, 900);
        $this->assertEquals(0, $ahmed->debt());
        $this->assertPostpaidBoxMatchesDebts(0);

        $lastPayment->delete();
        $this->assertEquals(900, $ahmed->debt());
        $this->assertPostpaidBoxMatchesDebts(900);
    }

    /**
     * EN: Deleting a postpaid order takes its debt off its customer and its money out of the postpaid box together.
     *     A paid order is deleted after its payment is deleted, and then its customer and the box are back to before the order.
     * AR: حذف أوردر آجل يشيل دينه من زبونه وفلوسه من صندوق الآجل سوية.
     *     الأوردر المسدد ينحذف بعد ما ينحذف تسديده، وبعدها زبونه والصندوق يرجعون مثل قبل الأوردر.
     */
    public function test_deleting_an_order_takes_its_debt_out_of_the_postpaid_box(): void
    {
        $ahmed = Customer::factory()->create();
        $sara = Customer::factory()->create();
        $this->sell($ahmed, OrderPaymentType::Postpaid, 1000);
        $unpaidOrder = $this->sell($ahmed, OrderPaymentType::Postpaid, 300);
        $this->sell($sara, OrderPaymentType::Postpaid, 500);

        $unpaidOrder->delete();
        $this->assertEquals(1000, $ahmed->debt());
        $this->assertPostpaidBoxMatchesDebts(1500);

        $ali = Customer::factory()->create();
        $paidOrder = $this->sell($ali, OrderPaymentType::Postpaid, 700);
        $payment = $this->pay($ali, 400);
        $this->assertPostpaidBoxMatchesDebts(1800);

        $payment->delete();
        $paidOrder->fresh()->delete();
        $this->assertEquals(0, $ali->debt());
        $this->assertPostpaidBoxMatchesDebts(1500);
    }

    /**
     * EN: Editing orders moves the debt and the postpaid box together. Given Ahmed's postpaid order of 1,000 with 400 paid,
     *     Sara's unpaid postpaid order of 500, and Ali's cash order of 900: Ahmed's order grows to 2,000 (he owes 1,600),
     *     Sara's becomes cash (she owes nothing), and Ali's becomes postpaid (he owes 900). The box and the debts are 2,500.
     * AR: تعديل الأوردرات يحرك الدين وصندوق الآجل سوية. عندنا أوردر آجل لأحمد بـ 1,000 مسدد منه 400،
     *     وأوردر آجل لسارة بـ 500 ما مسدد، وأوردر كاش لعلي بـ 900: أوردر أحمد يكبر لـ 2,000 (يصير مطلوب 1,600)،
     *     وأوردر سارة يصير كاش (ما مطلوبة شي)، وأوردر علي يصير آجل (يصير مطلوب 900). الصندوق والديون يصيرون 2,500.
     */
    public function test_editing_orders_keeps_the_postpaid_box_equal_to_the_debts(): void
    {
        $ahmed = Customer::factory()->create();
        $sara = Customer::factory()->create();
        $ali = Customer::factory()->create();
        $ahmedsOrder = $this->sell($ahmed, OrderPaymentType::Postpaid, 1000);
        $sarasOrder = $this->sell($sara, OrderPaymentType::Postpaid, 500);
        $alisOrder = $this->sell($ali, OrderPaymentType::Cash, 900);
        $this->pay($ahmed, 400);
        $this->assertPostpaidBoxMatchesDebts(1100);

        $this->editOrder($ahmedsOrder, quantity: 2);
        $this->editOrder($sarasOrder, paymentType: OrderPaymentType::Cash);
        $this->editOrder($alisOrder, paymentType: OrderPaymentType::Postpaid);

        $this->assertEquals(1600, $ahmed->debt());
        $this->assertEquals(0, $sara->debt());
        $this->assertEquals(900, $ali->debt());
        $this->assertPostpaidBoxMatchesDebts(2500);
    }

    /**
     * EN: Edits the order's one line to the quantity, or its payment type, through the edit action, keeping the rest as it is.
     * AR: يعدل كمية البند الوحيد بالأوردر، أو طريقة دفعه، عن طريق action التعديل، والباقي يبقى مثل ما هو.
     */
    private function editOrder(Order $order, float $quantity = 1, ?OrderPaymentType $paymentType = null): void
    {
        $order = $order->fresh();

        (new UpdateOrder)->handle($order, new CheckoutData(
            customerId: $order->customer_id,
            paymentType: $paymentType ?? $order->payment_type,
            date: $order->date->toDateString(),
            lines: [new CheckoutLine($order->items()->sole()->item_id, quantity: $quantity)],
        ));
    }

    /**
     * EN: The postpaid box and the sum of every customer's debt are both the expected amount.
     * AR: صندوق الآجل ومجموع ديون كل الزبائن الثنين يساوون المبلغ المتوقع.
     */
    private function assertPostpaidBoxMatchesDebts(float $expected): void
    {
        $postpaidBox = Order::boxBalance(OrderPaymentType::Postpaid, $this->allTime());
        $customersDebts = (float) Customer::query()->get()->sum(fn (Customer $customer): float => $customer->debt());

        $this->assertEquals($expected, $postpaidBox, 'The postpaid box is not the expected amount.');
        $this->assertEquals($expected, $customersDebts, 'The sum of the customers\' debts is not the expected amount.');
    }

    /**
     * EN: Sells one item of the given price to the customer through the checkout, with the given payment type.
     * AR: يبيع مادة وحدة بالسعر المحدد للزبون عن طريق الـ checkout، بطريقة الدفع المحددة.
     */
    private function sell(Customer $customer, OrderPaymentType $paymentType, float $price): Order
    {
        $item = Item::factory()->stocked()->create(['sale_price' => $price]);

        return (new Checkout)->handle(User::factory()->cashier()->create(), new CheckoutData(
            customerId: $customer->id,
            paymentType: $paymentType,
            date: '2026-09-01',
            lines: [new CheckoutLine($item->id, quantity: 1)],
        ));
    }

    /**
     * EN: Records a payment of the amount for the customer, made by a super user.
     * AR: يسجل تسديد بالمبلغ للزبون، يسويه يوزر سوبر.
     */
    private function pay(Customer $customer, float $amount): CustomerPayment
    {
        return (new PayCustomerDebt)->handle(User::factory()->create(['is_super' => true]), $customer, $amount, '2026-09-10');
    }

    /**
     * EN: A period of every date.
     * AR: فترة تشمل كل التواريخ.
     */
    private function allTime(): Period
    {
        return new Period(null, null);
    }
}
