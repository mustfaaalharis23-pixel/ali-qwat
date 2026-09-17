<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Enums\OrderPaymentType;
use App\Metrics\Period;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentOrder;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SpecificDescription;
use App\Models\User;
use App\Orders\PayCustomerDebt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayCustomerDebtTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The amount is spread on the customer's postpaid orders that have a remaining, from the oldest date to the newest, each getting up to its remaining.
     *     A partly paid order gets only what remains on it. Cash orders, fully paid orders, deleted orders, and other customers' orders are skipped.
     * AR: المبلغ يتوزع على أوردرات الزبون الآجلة اللي بيها باقي، من الأقدم تاريخاً للأحدث، وكل أوردر ياخذ لحد الباقي عليه.
     *     الأوردر المسدد جزئياً ياخذ بس الباقي عليه. أوردرات الكاش والمسددة بالكامل والمحذوفة وأوردرات الزبائن الثانيين ما تنسدد.
     */
    public function test_amount_is_spread_from_the_oldest_postpaid_order_to_the_newest(): void
    {
        $customer = Customer::factory()->create();
        $fullyPaid = $this->order($customer, '2026-06-20', 200);
        $partlyPaid = $this->order($customer, '2026-07-01', 1000);
        $middle = $this->order($customer, '2026-08-01', 500);
        $newest = $this->order($customer, '2026-09-01', 800);
        $cash = $this->order($customer, '2026-05-01', 900, OrderPaymentType::Cash);
        $deleted = $this->order($customer, '2026-04-01', 700);
        $deleted->delete();
        $otherCustomer = $this->order(Customer::factory()->create(), '2026-03-01', 300);
        (new PayCustomerDebt)->handle($this->superUser(), $customer, 600, '2026-09-05');

        $payment = (new PayCustomerDebt)->handle($this->superUser(), $customer, 900, '2026-09-10');

        $this->assertEquals(
            [$partlyPaid->id => 600, $middle->id => 300],
            $payment->allocations()->pluck('amount', 'order_id')->all(),
        );
        $this->assertEquals(0, $fullyPaid->fresh()->remaining);
        $this->assertEquals(0, $partlyPaid->fresh()->remaining);
        $this->assertEquals(200, $middle->fresh()->remaining);
        $this->assertEquals(800, $newest->fresh()->remaining);
        $this->assertEquals(0, $cash->fresh()->remaining);
        $this->assertEquals(700, Order::withTrashed()->find($deleted->id)->remaining);
        $this->assertEquals(300, $otherCustomer->fresh()->remaining);
    }

    /**
     * EN: A payment creates an input invoice with no cashier and no images, with one line of the amount under the postpaid transfer description.
     *     So the postpaid box goes down by the amount, the accountant's balance goes up by it, and the customer's debt goes down by it.
     * AR: التسديد ينشئ فاتورة داخل بدون كاشير وبدون صور، بيها بند واحد بالمبلغ تحت وصف تحويل الآجل.
     *     فصندوق الآجل ينقص بالمبلغ، ورصيد المحاسب يزيد بيه، ودين الزبون ينقص بيه.
     */
    public function test_payment_moves_the_amount_from_the_postpaid_box_to_the_accountant_balance(): void
    {
        $actor = $this->superUser();
        $customer = Customer::factory()->create();
        $this->order($customer, '2026-08-01', 1000);
        $description = SpecificDescription::query()->where('transfer_type', OrderPaymentType::Postpaid)->firstOrFail();

        $payment = (new PayCustomerDebt)->handle($actor, $customer, 400, '2026-09-10', 'customer-payments/receipt.jpg', 'Paid at the shop')->fresh();

        $this->assertSame($customer->id, $payment->customer_id);
        $this->assertSame($actor->id, $payment->user_id);
        $this->assertEquals(400, $payment->amount);
        $this->assertSame('2026-09-10', $payment->date->toDateString());
        $this->assertSame('customer-payments/receipt.jpg', $payment->image);
        $this->assertSame('Paid at the shop', $payment->notes);

        $invoice = $payment->invoice;
        $this->assertSame(InvoiceType::Input, $invoice->type);
        $this->assertNull($invoice->cashier_id);
        $this->assertSame([], $invoice->images);
        $this->assertEquals(400, $invoice->amount);
        $this->assertSame([$description->id], $invoice->items()->pluck('specific_description_id')->all());

        $this->assertEquals(600, Order::boxBalance(OrderPaymentType::Postpaid, $this->allTime()));
        $this->assertEquals(400, Invoice::accountantBalance($this->allTime()));
        $this->assertEquals(600, $customer->debt());
    }

    /**
     * EN: Amounts that cannot be paid on a debt of 1000: more than the debt, zero, and negative.
     * AR: مبالغ ما تنسدد على دين 1000: أكثر من الدين، صفر، وسالب.
     *
     * @return array<string, array{float}>
     */
    public static function amountsThatCannotBePaid(): array
    {
        return [
            'more than the debt' => [1001],
            'zero' => [0],
            'negative' => [-50],
        ];
    }

    /**
     * EN: An amount more than the customer's whole debt, or not more than zero, is rejected, and nothing is saved.
     * AR: المبلغ الأكثر من دين الزبون كله، أو اللي مو أكبر من صفر، ينرفض، وما ينحفظ شي.
     */
    #[DataProvider('amountsThatCannotBePaid')]
    public function test_amount_that_cannot_be_paid_is_rejected_and_nothing_is_saved(float $amount): void
    {
        $customer = Customer::factory()->create();
        $this->order($customer, '2026-07-01', 600);
        $this->order($customer, '2026-08-01', 400);

        try {
            (new PayCustomerDebt)->handle($this->superUser(), $customer, $amount, '2026-09-10');
            $this->fail('An amount that cannot be paid must be rejected.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(0, CustomerPayment::query()->count());
        $this->assertSame(0, CustomerPaymentOrder::query()->count());
        $this->assertSame(0, Invoice::query()->revenues()->count());
    }

    /**
     * EN: Deleting a payment deletes its allocations and its invoice, so the orders' remaining, the postpaid box, and the accountant's balance go back to what they were.
     * AR: حذف التسديد يحذف توزيعاته وفاتورته، فباقي الأوردرات وصندوق الآجل ورصيد المحاسب يرجعون مثل ما كانوا.
     */
    public function test_deleting_a_payment_restores_the_debt_and_both_boxes(): void
    {
        $customer = Customer::factory()->create();
        $first = $this->order($customer, '2026-07-01', 600);
        $second = $this->order($customer, '2026-08-01', 400);
        $payment = (new PayCustomerDebt)->handle($this->superUser(), $customer, 800, '2026-09-10');

        $payment->delete();

        $this->assertSoftDeleted($payment);
        $this->assertSoftDeleted($payment->invoice);
        $this->assertSame(0, CustomerPaymentOrder::query()->count());
        $this->assertEquals(600, $first->fresh()->remaining);
        $this->assertEquals(400, $second->fresh()->remaining);
        $this->assertEquals(1000, Order::boxBalance(OrderPaymentType::Postpaid, $this->allTime()));
        $this->assertEquals(0, Invoice::accountantBalance($this->allTime()));
    }

    /**
     * EN: An order that has a payment on it cannot be deleted, and the invoice of a payment cannot be deleted on its own; both stay.
     * AR: الأوردر اللي عليه تسديد ما ينحذف، وفاتورة التسديد ما تنحذف لوحدها؛ الثنين يبقون.
     */
    public function test_paid_order_and_payment_invoice_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer, '2026-08-01', 1000);
        $payment = (new PayCustomerDebt)->handle($this->superUser(), $customer, 400, '2026-09-10');

        try {
            $order->delete();
            $this->fail('An order with a payment must not be deleted.');
        } catch (ValidationException) {
            //
        }

        try {
            $payment->invoice->delete();
            $this->fail('The invoice of a payment must not be deleted on its own.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSoftDeleted($order);
        $this->assertNotSoftDeleted($payment->invoice);
    }

    /**
     * EN: Each user is missing one of the two permissions a payment needs.
     * AR: كل يوزر ناقصته وحدة من الصلاحيتين اللي يحتاجهن التسديد.
     *
     * @return array<string, array{list<string>}>
     */
    public static function usersMissingOnePermission(): array
    {
        return [
            'without customer_payment.create' => [['invoice.create']],
            'without invoice.create' => [['customer_payment.create']],
        ];
    }

    /**
     * EN: A user needs both customer_payment.create and invoice.create; missing one of them rejects the payment, and nothing is saved.
     * AR: اليوزر يحتاج customer_payment.create و invoice.create سوية؛ إذا ناقصته وحدة ينرفض التسديد، وما ينحفظ شي.
     *
     * @param  list<string>  $permissions
     */
    #[DataProvider('usersMissingOnePermission')]
    public function test_user_needs_both_permissions_to_pay(array $permissions): void
    {
        $actor = User::factory()->create();
        DB::table('user_permissions')->insert(array_map(
            fn (string $permission): array => ['user_id' => $actor->id, 'permission' => $permission],
            $permissions,
        ));
        $customer = Customer::factory()->create();
        $this->order($customer, '2026-08-01', 1000);

        try {
            (new PayCustomerDebt)->handle($actor, $customer, 500, '2026-09-10');
            $this->fail('A user missing a permission must not pay.');
        } catch (AuthorizationException) {
            //
        }

        $this->assertSame(0, CustomerPayment::query()->count());
        $this->assertSame(0, Invoice::query()->revenues()->count());
    }

    /**
     * EN: Creates an order of the customer on the given date, postpaid unless another payment type is given, with one line of the given amount.
     * AR: ينشئ أوردر للزبون بالتاريخ المحدد، آجل إلا إذا انطت طريقة دفع ثانية، بيه بند واحد بالمبلغ المحدد.
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
     * EN: A period of every date.
     * AR: فترة تشمل كل التواريخ.
     */
    private function allTime(): Period
    {
        return new Period(null, null);
    }

    /**
     * EN: Creates a super user.
     * AR: ينشئ يوزر is_super.
     */
    private function superUser(): User
    {
        return User::factory()->create(['is_super' => true]);
    }
}
