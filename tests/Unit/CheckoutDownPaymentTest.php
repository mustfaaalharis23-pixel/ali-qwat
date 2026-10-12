<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Metrics\Period;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use App\Orders\PayCustomerDebt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutDownPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A postpaid sale with a down payment keeps the amount on the order, and records one payment marked as a down payment,
     *     by the seller, on the order's date, for the order's customer. The order is paid by it and the rest stays owed.
     * AR: البيعة الآجلة بدفعة أولية تحفظ المبلغ على الأوردر، وتسجّل تسديد واحد معلّم إنه دفعة أولية،
     *     باسم البائع، بتاريخ الأوردر، لزبون الأوردر. الأوردر ينسدد بيه والباقي يضل دين.
     */
    public function test_postpaid_sale_with_a_down_payment_records_it_on_the_order_and_as_a_payment(): void
    {
        $seller = User::factory()->create();
        $customer = Customer::factory()->create();

        $order = (new Checkout)->handle($seller, $this->postpaidSale($customer, total: 1000, downPayment: 300))->fresh();

        $this->assertEquals(300, $order->down_payment);
        $this->assertEquals(300, $order->paid_amount);
        $this->assertEquals(700, $order->remaining);

        $payment = CustomerPayment::query()->sole();
        $this->assertTrue($payment->is_down_payment);
        $this->assertEquals(300, $payment->amount);
        $this->assertSame($customer->id, $payment->customer_id);
        $this->assertSame($seller->id, $payment->user_id);
        $this->assertSame($order->date->toDateString(), $payment->date->toDateString());
        $this->assertEquals([$order->id => 300], $payment->allocations()->pluck('amount', 'order_id')->all());
    }

    /**
     * EN: The down payment goes to the new order only, even when the customer owes older orders; they keep their remaining.
     * AR: الدفعة الأولية تروح للأوردر الجديد بس، حتى لو الزبون عليه أوردرات أقدم؛ هذيچ يضل باقيها مثل ما هو.
     */
    public function test_down_payment_goes_to_the_new_order_only_and_not_to_older_debts(): void
    {
        $customer = Customer::factory()->create();
        $olderDebt = $this->existingPostpaidOrder($customer, amount: 500);

        $order = (new Checkout)->handle(User::factory()->create(), $this->postpaidSale($customer, total: 1000, downPayment: 300));

        $this->assertEquals(500, $olderDebt->fresh()->remaining);
        $this->assertEquals(700, $order->fresh()->remaining);
        $this->assertEquals(1200, $customer->debt());
    }

    /**
     * EN: The down payment moves money like any payment: out of the postpaid box and into the accountant's balance,
     *     so the postpaid box still equals what the customers owe.
     * AR: الدفعة الأولية تحرّك الفلوس مثل أي تسديد: تطلع من صندوق الآجل وتدخل لرصيد المحاسب،
     *     فصندوق الآجل يضل يساوي اللي على الزبائن.
     */
    public function test_down_payment_moves_the_money_from_the_postpaid_box_to_the_accountant_balance(): void
    {
        $customer = Customer::factory()->create();

        (new Checkout)->handle(User::factory()->create(), $this->postpaidSale($customer, total: 1000, downPayment: 300));

        $this->assertEquals(700, Order::boxBalance(OrderPaymentType::Postpaid, $this->allTime()));
        $this->assertEquals(300, Invoice::accountantBalance($this->allTime()));
        $this->assertEquals(700, $customer->debt());
    }

    /**
     * EN: A postpaid sale without a down payment has no down payment on the order, and no payment or invoice is created.
     * AR: البيعة الآجلة بدون دفعة أولية ما بيها دفعة أولية على الأوردر، وما ينشأ لا تسديد ولا فاتورة.
     */
    public function test_postpaid_sale_without_a_down_payment_creates_no_payment(): void
    {
        $order = (new Checkout)->handle(User::factory()->create(), $this->postpaidSale(total: 1000, downPayment: null))->fresh();

        $this->assertNull($order->down_payment);
        $this->assertEquals(1000, $order->remaining);
        $this->assertSame(0, CustomerPayment::query()->count());
        $this->assertSame(0, Invoice::query()->revenues()->count());
    }

    /**
     * EN: A down payment equal to the whole order is accepted: the order is fully paid and nothing stays owed.
     * AR: الدفعة الأولية اللي تساوي الأوردر كله تنقبل: الأوردر ينسدد كامل وما يضل عليه شي.
     */
    public function test_down_payment_equal_to_the_whole_order_is_accepted(): void
    {
        $order = (new Checkout)->handle(User::factory()->create(), $this->postpaidSale(total: 1000, downPayment: 1000))->fresh();

        $this->assertEquals(1000, $order->down_payment);
        $this->assertEquals(0, $order->remaining);
    }

    /**
     * EN: Down payments that cannot be taken on a postpaid order of 1000: more than the order, zero, and negative.
     * AR: دفعات أولية ما تنقبل على أوردر آجل بـ 1000: أكثر من الأوردر، صفر، وسالب.
     *
     * @return array<string, array{float}>
     */
    public static function downPaymentsThatCannotBeTaken(): array
    {
        return [
            'more than the order' => [1001],
            'zero' => [0],
            'negative' => [-50],
        ];
    }

    /**
     * EN: A down payment more than the order, or not more than zero, rejects the whole sale, and nothing is saved.
     * AR: الدفعة الأولية الأكثر من الأوردر، أو اللي مو أكبر من صفر، ترفض البيعة كلها، وما ينحفظ شي.
     */
    #[DataProvider('downPaymentsThatCannotBeTaken')]
    public function test_down_payment_that_cannot_be_taken_rejects_the_whole_sale(float $downPayment): void
    {
        $this->assertSaleIsRejected($this->postpaidSale(total: 1000, downPayment: $downPayment));
    }

    /**
     * EN: A down payment is for postpaid sales only: a cash sale with a down payment is rejected, and nothing is saved.
     * AR: الدفعة الأولية للبيع الآجل بس: البيعة الكاش ويا دفعة أولية تنرفض، وما ينحفظ شي.
     */
    public function test_cash_sale_with_a_down_payment_is_rejected(): void
    {
        $this->assertSaleIsRejected($this->postpaidSale(total: 1000, downPayment: 300, paymentType: OrderPaymentType::Cash));
    }

    /**
     * EN: When a line of the sale fails (more than the stock), the down payment is not saved either: no order, no payment, no invoice.
     * AR: إذا فشل بند من البيعة (أكثر من المخزون)، الدفعة الأولية هم ما تنحفظ: لا أوردر، لا تسديد، لا فاتورة.
     */
    public function test_down_payment_is_not_saved_when_the_sale_fails(): void
    {
        $item = Item::factory()->stocked(5)->create(['sale_price' => 1000]);

        $this->assertSaleIsRejected(new CheckoutData(
            customerId: Customer::factory()->create()->id,
            paymentType: OrderPaymentType::Postpaid,
            date: '2026-09-18',
            lines: [new CheckoutLine($item->id, quantity: 6)],
            downPayment: 300,
        ));
    }

    /**
     * EN: The seller takes the down payment with the sale itself, without the payment permissions a normal payment needs.
     * AR: البائع ياخذ الدفعة الأولية ويا البيعة نفسها، بدون صلاحيات التسديد اللي يحتاجها التسديد العادي.
     */
    public function test_seller_without_payment_permissions_can_take_a_down_payment(): void
    {
        $sellerWithoutPermissions = User::factory()->create();

        $order = (new Checkout)->handle($sellerWithoutPermissions, $this->postpaidSale(total: 1000, downPayment: 300));

        $this->assertEquals(300, $order->fresh()->down_payment);
        $this->assertSame(1, CustomerPayment::query()->count());
    }

    /**
     * EN: A later payment on the order is a normal payment, not a down payment, and the order keeps its down payment amount as it was.
     * AR: التسديد اللي يجي بعدين على الأوردر تسديد عادي، مو دفعة أولية، والأوردر يضل محتفظ بمبلغ دفعته الأولية مثل ما هو.
     */
    public function test_a_later_payment_is_not_a_down_payment_and_does_not_change_the_order_down_payment(): void
    {
        $customer = Customer::factory()->create();
        $order = (new Checkout)->handle(User::factory()->create(), $this->postpaidSale($customer, total: 1000, downPayment: 300));

        $laterPayment = (new PayCustomerDebt)->handle(User::factory()->create(['is_super' => true]), $customer, 200, '2026-09-20');

        $this->assertFalse($laterPayment->fresh()->is_down_payment);
        $this->assertEquals(300, $order->fresh()->down_payment);
        $this->assertEquals(500, $order->fresh()->remaining);
    }

    /**
     * EN: Asserts that the checkout throws a validation error and saves no order, no item, no payment, and no invoice.
     * AR: يتأكد إن الچيك اوت يرمي خطأ تحقق وما يحفظ لا أوردر ولا بند ولا تسديد ولا فاتورة.
     */
    private function assertSaleIsRejected(CheckoutData $data): void
    {
        try {
            (new Checkout)->handle(User::factory()->create(), $data);

            $this->fail('The sale was not rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
            $this->assertDatabaseCount('customer_payments', 0);
            $this->assertSame(0, Invoice::query()->revenues()->count());
        }
    }

    /**
     * EN: Builds a sale of one line worth the given total, postpaid unless another payment type is given, for the customer or a new one.
     * AR: يبني بيعة ببند واحد قيمته المجموع المحدد، آجلة إلا إذا انطت طريقة دفع ثانية، للزبون أو لزبون جديد.
     */
    private function postpaidSale(
        ?Customer $customer = null,
        float $total = 1000,
        ?float $downPayment = null,
        OrderPaymentType $paymentType = OrderPaymentType::Postpaid,
    ): CheckoutData {
        $item = Item::factory()->stocked()->create(['sale_price' => $total]);

        return new CheckoutData(
            customerId: ($customer ?? Customer::factory()->create())->id,
            paymentType: $paymentType,
            date: '2026-09-18',
            lines: [new CheckoutLine($item->id, quantity: 1)],
            downPayment: $downPayment,
        );
    }

    /**
     * EN: Creates an older postpaid order of the customer, with one line of the given amount and nothing paid on it.
     * AR: ينشئ أوردر آجل أقدم للزبون، ببند واحد بالمبلغ المحدد وما مدفوع عليه شي.
     */
    private function existingPostpaidOrder(Customer $customer, float $amount): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_type' => OrderPaymentType::Postpaid,
            'date' => '2026-08-01',
        ]);

        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'price' => $amount]);

        return $order->fresh();
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
