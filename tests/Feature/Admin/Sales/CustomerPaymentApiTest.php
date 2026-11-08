<?php

namespace Tests\Feature\Admin\Sales;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Orders\PayCustomerDebt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CustomerPaymentApiTest extends SalesTestCase
{
    /**
     * EN: The payment form of a customer lists the orders it still owes on, oldest first, with what is left on each, and its debt;
     *     opened on one order, it lists that order only.
     * AR: فورم التسديد للزبون يعرض الأوردرات اللي بعده مديون بيها، من الأقدم، ويا الباقي على كل وحدة، ودينه؛
     *     وإذا انفتح على أوردر واحد، يعرض بس هذا الأوردر.
     */
    public function test_the_payment_form_lists_the_open_orders(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ali']);
        $newer = $this->order($customer, 500, date: '2026-09-05');
        $older = $this->order($customer, 1000, date: '2026-09-01');
        $this->order($customer, 700, OrderPaymentType::Cash);
        $this->actingAs($this->userWith('customer_payment.view', 'customer_payment.create', 'invoice.create'));

        $this->getJson("/admin-api/customer-payments/form?customer_id={$customer->id}")
            ->assertOk()
            ->assertJsonPath('customer.name', 'Ali')
            ->assertJsonPath('debt', 1500)
            ->assertJsonPath('orders.0.id', $older->id)
            ->assertJsonPath('orders.0.remaining', 1000)
            ->assertJsonPath('orders.1.id', $newer->id);

        $this->getJson("/admin-api/customer-payments/form?order_id={$newer->id}")
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $newer->id);
    }

    /**
     * EN: A payment is recorded on the chosen orders with its receipt image, and comes back with its receipt link;
     *     an amount more than what is left on an order is refused.
     * AR: التسديد ينسجل على الأوردرات المختارة ويا صورة وصله، ويرجع ويا رابط وصله؛
     *     والمبلغ الأكثر من الباقي على الأوردر ينرفض.
     */
    public function test_a_payment_is_recorded_on_the_orders(): void
    {
        Storage::fake('public');
        $customer = Customer::factory()->create();
        $first = $this->order($customer, 1000, date: '2026-09-01');
        $second = $this->order($customer, 500, date: '2026-09-05');
        $this->actingAs($this->userWith('customer_payment.view', 'customer_payment.create', 'invoice.create'));

        $response = $this->post('/admin-api/customer-payments', [
            'customer_id' => $customer->id,
            'amounts' => [$first->id => '1,000', $second->id => '200'],
            'date' => '2026-09-10',
            'notes' => 'Cash at the shop',
            'image' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $payment = CustomerPayment::query()->findOrFail($response->json('data.id'));
        $this->assertEquals(1200, $payment->amount);
        $this->assertSame(route('customer-payments.receipt', $payment), $response->json('data.receipt_url'));
        Storage::disk('public')->assertExists($payment->image);
        $this->assertEquals(0, $first->fresh()->remaining);
        $this->assertEquals(300, $second->fresh()->remaining);

        $this->postJson('/admin-api/customer-payments', [
            'customer_id' => $customer->id,
            'amounts' => [$second->id => 900],
            'date' => '2026-09-10',
        ])->assertUnprocessable();
    }

    /**
     * EN: Recording a payment needs the payment and the invoice permissions.
     * AR: تسجيل التسديد يحتاج صلاحية التسديد وصلاحية الفاتورة.
     */
    public function test_recording_needs_the_permissions(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer, 1000);

        $this->actingAs($this->userWith('customer_payment.view'))
            ->postJson('/admin-api/customer-payments', ['customer_id' => $customer->id, 'amounts' => [$order->id => 100], 'date' => '2026-09-10'])
            ->assertForbidden();
    }

    /**
     * EN: The payments list is filtered by customer and period; a payment shows its paid orders; a deleted payment gives its orders
     *     their remaining back and has no receipt.
     * AR: قائمة التسديدات تنفلتر بالزبون والفترة؛ والتسديد يبين الأوردرات اللي سددها؛ والتسديد المحذوف يرجّع الباقي لأوردراته
     *     وما إله وصل.
     */
    public function test_list_show_and_delete_payments(): void
    {
        $ali = Customer::factory()->create(['name' => 'Ali']);
        $order = $this->order($ali, 1000);
        $actor = $this->superUser();
        $september = (new PayCustomerDebt)->payOrders($actor, $ali, [$order->id => 300], '2026-09-10');
        (new PayCustomerDebt)->payOrders($actor, $ali, [$order->id => 100], '2026-10-10');
        $omar = Customer::factory()->create();
        (new PayCustomerDebt)->payOrders($actor, $omar, [$this->order($omar, 500)->id => 100], '2026-09-12');
        $this->actingAs($this->userWith('customer_payment.view', 'customer_payment.delete'));

        $this->getJson("/admin-api/customer-payments?filter[customer_id]={$ali->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/admin-api/customer-payments?filter[from]=2026-09-01&filter[until]=2026-09-30')->assertJsonCount(2, 'data');

        $this->getJson("/admin-api/customer-payments/{$september->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Ali')
            ->assertJsonPath('data.allocations.0.order_id', $order->id)
            ->assertJsonPath('data.allocations.0.amount', 300);

        $this->deleteJson("/admin-api/customer-payments/{$september->id}")->assertNoContent();
        $this->assertEquals(900, $order->fresh()->remaining);
        $this->getJson("/admin-api/customer-payments/{$september->id}")->assertOk()->assertJsonPath('data.receipt_url', null);
    }

    /**
     * EN: The statement of a customer has a previous balance before the from date, its postpaid orders and payments with the balance
     *     after each, the cards of the period, and the orders tree with the payments under each order.
     * AR: كشف حساب الزبون بيه رصيد سابق قبل تاريخ البداية، وأوردراته الآجلة وتسديداته ويا الرصيد بعد كل وحدة، وبطاقات الفترة،
     *     وشجرة الأوردرات ويا التسديدات تحت كل أوردر.
     */
    public function test_the_statement_of_a_customer(): void
    {
        $customer = Customer::factory()->create();
        $old = $this->order($customer, 400, date: '2026-08-01');
        $order = $this->order($customer, 1000, date: '2026-09-01');
        $this->order($customer, 999, OrderPaymentType::Cash, '2026-09-02');
        (new PayCustomerDebt)->payOrders($this->superUser(), $customer, [$order->id => 300], '2026-09-05');
        $this->actingAs($this->userWith('customer_payment.view'));

        $response = $this->getJson("/admin-api/customer-payments/statement?customer={$customer->id}&from=2026-09-01")->assertOk();

        $this->assertSame([400, 1400, 1100], array_map(fn ($balance) => (int) $balance, array_column($response->json('rows'), 'balance')));
        $this->assertSame('opening_balance', $response->json('rows.0.type'));
        $this->assertEquals(1000, $response->json('summary.orders'));
        $this->assertEquals(300, $response->json('summary.payments'));
        $this->assertEquals(1100, $response->json('summary.balance'));
        $this->assertCount(1, $response->json('orders'));
        $this->assertSame($order->id, $response->json('orders.0.id'));
        $this->assertEquals(700, $response->json('orders.0.payments.0.left_after'));
        $this->assertNotSame($old->id, $response->json('orders.0.id'));

        $this->getJson('/admin-api/customer-payments/statement')->assertOk()->assertJsonPath('customer', null);
    }

    /**
     * EN: The reminders tabs list the customers with a weekly payment due today, and the late ones with the days late.
     * AR: تبويبات التذكير تعرض الزبائن اللي عليهم قسط أسبوعي اليوم، والمتأخرين ويا أيام التأخير.
     */
    public function test_the_weekly_payment_reminders(): void
    {
        $this->travelTo('2026-09-15');
        $late = Customer::factory()->create(['name' => 'Late']);
        $this->order($late, 1000, date: '2026-09-01', attributes: ['weekly_payment' => 100]);
        $dueToday = Customer::factory()->create(['name' => 'Today']);
        $this->order($dueToday, 1000, date: '2026-09-08', attributes: ['weekly_payment' => 100]);
        $this->actingAs($this->userWith('customer_payment.view'));

        $this->getJson('/admin-api/customer-payments/reminders?late=0')
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.1.customer.name', 'Today')
            ->assertJsonPath('rows.1.amount', 100);

        $this->getJson('/admin-api/customer-payments/reminders?late=1')
            ->assertOk()
            ->assertJsonPath('rows.0.customer.name', 'Late')
            ->assertJsonPath('rows.0.days_late', 7);

        $this->getJson('/admin-api/customer-payments/overview')
            ->assertOk()
            ->assertJsonPath('counts.due_today', 2)
            ->assertJsonPath('counts.late', 1);
    }

    /**
     * EN: Without the view permission the customer payments page is forbidden.
     * AR: بدون صلاحية العرض صفحة تسديدات الزبائن ممنوعة.
     */
    public function test_the_page_needs_the_permission(): void
    {
        $this->actingAs($this->userWith())->getJson('/admin-api/customer-payments')->assertForbidden();
        $this->getJson('/admin-api/customer-payments/overview')->assertForbidden();
    }
}
