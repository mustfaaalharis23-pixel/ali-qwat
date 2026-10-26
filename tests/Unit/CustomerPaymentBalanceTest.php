<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Orders\PayCustomerDebt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPaymentBalanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The previous balance on a payment's receipt is what the customer owed just before it, the same as the account statement:
     *     the postpaid orders up to the payment's date minus the payments before it. The remaining is the previous balance minus the payment.
     *     Cash orders, deleted orders, deleted payments, later orders, and other customers' orders are not counted.
     * AR: الحساب السابق بسند التسديد هو اللي كان على الزبون قبل التسديد بالضبط، نفس كشف الحساب:
     *     الطلبات الآجلة لحد تاريخ التسديد ناقص التسديدات اللي قبله. والمتبقي هو الحساب السابق ناقص مبلغ التسديد.
     *     طلبات الكاش والطلبات المحذوفة والتسديدات المحذوفة والطلبات الأحدث وطلبات الزبائن الثانيين ما تنحسب.
     */
    public function test_previous_balance_and_remaining_follow_the_account_statement(): void
    {
        $customer = Customer::factory()->create();
        $this->order($customer, '2026-07-01', 1000);
        $this->order($customer, '2026-07-05', 900, OrderPaymentType::Cash);
        $this->order($customer, '2026-08-01', 500);
        $this->order($customer, '2026-08-02', 400)->delete();
        $this->order($customer, '2026-09-01', 800);
        $this->order(Customer::factory()->create(), '2026-07-01', 700);

        $first = $this->pay($customer, 300, '2026-07-10');
        $this->pay($customer, 100, '2026-08-10')->delete();
        $second = $this->pay($customer, 200, '2026-08-15');

        $this->assertEquals(1000, $first->balanceBefore());
        $this->assertEquals(700, $first->balanceAfter());
        $this->assertEquals(1200, $second->balanceBefore());
        $this->assertEquals(1000, $second->balanceAfter());
    }

    /**
     * EN: On the same date, as in the account statement, the orders of that day come before the payments, even an order recorded after the payment,
     *     and the payments of that day follow the order they were recorded in.
     * AR: بنفس التاريخ، مثل كشف الحساب، طلبات ذاك اليوم تجي قبل التسديدات، حتى الطلب اللي انسجل بعد التسديد،
     *     وتسديدات ذاك اليوم تمشي بترتيب تسجيلها.
     */
    public function test_on_the_same_date_orders_come_before_payments_and_payments_follow_their_order(): void
    {
        $customer = Customer::factory()->create();
        $this->order($customer, '2026-09-01', 1000);
        $earlier = $this->pay($customer, 200, '2026-09-05');
        $this->order($customer, '2026-09-05', 500);
        $later = $this->pay($customer, 300, '2026-09-05');

        $this->assertEquals(1500, $earlier->balanceBefore());
        $this->assertEquals(1300, $earlier->balanceAfter());
        $this->assertEquals(1300, $later->balanceBefore());
        $this->assertEquals(1000, $later->balanceAfter());
    }

    /**
     * EN: Creates an order of the customer on the date, with one line of the amount.
     * AR: ينشئ طلب للزبون بالتاريخ، بيه بند واحد بالمبلغ.
     */
    private function order(Customer $customer, string $date, float $amount, OrderPaymentType $paymentType = OrderPaymentType::Postpaid): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_type' => $paymentType,
            'date' => $date,
        ]);

        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'price' => $amount]);

        return $order->fresh();
    }

    /**
     * EN: Records a payment of the customer on the date, by a super user.
     * AR: يسجل تسديد للزبون بالتاريخ، من يوزر is_super.
     */
    private function pay(Customer $customer, float $amount, string $date): CustomerPayment
    {
        return (new PayCustomerDebt)->handle(User::factory()->create(['is_super' => true]), $customer, $amount, $date);
    }
}
