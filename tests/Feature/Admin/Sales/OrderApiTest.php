<?php

namespace Tests\Feature\Admin\Sales;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Order;
use App\Orders\PayCustomerDebt;
use Illuminate\Support\Facades\DB;

class OrderApiTest extends SalesTestCase
{
    /**
     * EN: The orders list is searched by the customer's name, filtered by the payment type, newest first, with the count of lines
     *     and what the user may do; the cost and the profit stay hidden from a user who cannot see the cost.
     * AR: قائمة الأوردرات تنبحث باسم الزبون، وتنفلتر بنوع الدفع، الأحدث أول، ويا عدد البنود والشي اللي يگدر اليوزر يسويه؛
     *     والكلفة والربح مخفية عن اليوزر اللي ما يگدر يشوف الكلفة.
     */
    public function test_the_list_is_searched_filtered_and_hides_the_cost(): void
    {
        $ali = Customer::factory()->create(['name' => 'Ali']);
        $older = $this->order($ali, 1000, OrderPaymentType::Postpaid, '2026-08-01');
        $newer = $this->order($ali, 2000, OrderPaymentType::Postpaid, '2026-09-01');
        $this->order($ali, 3000, OrderPaymentType::Cash, '2026-09-02');
        $this->order(Customer::factory()->create(['name' => 'Omar']), 500, OrderPaymentType::Postpaid);

        $response = $this->actingAs($this->userWith('order.view'))
            ->getJson('/admin-api/orders?search=ali&filter[payment_type]=postpaid')
            ->assertOk();

        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertSame('Ali', $response->json('data.0.customer.name'));
        $this->assertSame(1, $response->json('data.0.items_count'));
        $this->assertNull($response->json('data.0.total_cost'));
        $this->assertNull($response->json('data.0.profit'));
        $this->assertFalse($response->json('can.see_cost'));
        $this->assertFalse($response->json('can.create'));
        $this->assertTrue($response->json('data.0.can.view'));
    }

    /**
     * EN: A user who can see the cost gets the cost and the profit of each order.
     * AR: اليوزر اللي يگدر يشوف الكلفة ياخذ كلفة وربح كل أوردر.
     */
    public function test_the_cost_shows_to_a_user_who_can_see_it(): void
    {
        $this->order(Customer::factory()->create(), 1000);

        $response = $this->actingAs($this->userWith('order.view', 'item.view_cost_price'))->getJson('/admin-api/orders')->assertOk();

        $this->assertTrue($response->json('can.see_cost'));
        $this->assertNotNull($response->json('data.0.total_cost'));
        $this->getJson('/admin-api/sales/abilities')->assertOk()->assertJsonPath('see_cost', true)->assertJsonPath('create_order', false);
        $this->assertNotNull($response->json('data.0.profit'));
    }

    /**
     * EN: Without the view permission the orders cannot be listed or opened.
     * AR: بدون صلاحية العرض ما تنعرض الأوردرات ولا تنفتح.
     */
    public function test_viewing_needs_the_permission(): void
    {
        $order = $this->order(Customer::factory()->create());

        $this->actingAs($this->userWith())->getJson('/admin-api/orders')->assertForbidden();
        $this->getJson("/admin-api/orders/{$order->id}")->assertForbidden();
    }

    /**
     * EN: An order's page has its details, amounts, lines, its payments oldest first with their receipts, and its print links
     *     for a user who can open the invoice printing; the pay action shows while something is left on it.
     * AR: صفحة الأوردر بيها تفاصيله ومبالغه وبنوده وتسديداته من الأقدم ويا وصولاتها، وروابط طباعته
     *     لليوزر اللي يگدر يفتح طباعة الفواتير؛ وزر التسديد يطلع ما دام عليه باقي.
     */
    public function test_an_order_page_has_its_lines_payments_and_print_links(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ali']);
        $order = $this->order($customer, 1000, attributes: ['weekly_payment' => 100]);
        $payment = (new PayCustomerDebt)->payOrders($this->superUser(), $customer, [$order->id => 300], '2026-09-05');

        $response = $this->actingAs($this->userWith('order.view', 'invoice_printing.view', 'customer_payment.create', 'invoice.create'))
            ->getJson("/admin-api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Ali')
            ->assertJsonPath('data.payment_type.value', 'postpaid')
            ->assertJsonPath('data.paid_amount', 300)
            ->assertJsonPath('data.remaining', 700)
            ->assertJsonPath('data.payments.0.amount', 300)
            ->assertJsonPath('data.payments.0.receipt_url', route('customer-payments.receipt', $payment))
            ->assertJsonPath('data.print.a4', route('invoices.print', ['order' => $order, 'format' => 'a4']))
            ->assertJsonPath('data.print.pos', route('invoices.print', ['order' => $order, 'format' => 'pos']))
            ->assertJsonPath('data.can.pay', true)
            ->assertJsonPath('data.weekly.amount', 100);

        $this->assertCount(1, $response->json('data.lines'));
        $this->assertNull($response->json('data.lines.0.cost_price'));
    }

    /**
     * EN: Without the invoice printing permission the order has no print links.
     * AR: بدون صلاحية طباعة الفواتير الأوردر ما بيه روابط طباعة.
     */
    public function test_print_links_need_the_invoice_printing_permission(): void
    {
        $order = $this->order(Customer::factory()->create());

        $this->actingAs($this->userWith('order.view'))
            ->getJson("/admin-api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.print', null)
            ->assertJsonPath('data.can.pay', false);
    }

    /**
     * EN: A sale is made from the cart through the checkout: the price comes from the item, the percent discount comes off the
     *     total, and a down payment is recorded on a postpaid sale.
     * AR: البيع ينسوى من السلة عن طريق الـ checkout: السعر من المادة، وخصم النسبة ينطرح من المجموع،
     *     والعربون ينسجل ويا البيع الآجل.
     */
    public function test_a_sale_is_made_through_the_checkout(): void
    {
        $item = $this->stockedItem(salePrice: 1000);
        $customer = Customer::factory()->create();

        $id = $this->actingAs($this->superUser())->postJson('/admin-api/orders', [
            'customer_id' => $customer->id,
            'payment_type' => 'postpaid',
            'date' => '2026-09-10',
            'notes' => 'At the shop',
            'down_payment' => 500,
            'weekly_payment' => 1000,
            'discount_percent' => 10,
            'lines' => [['item_id' => $item->id, 'lens_power_id' => null, 'colors' => [], 'packets' => 5]],
        ])->assertCreated()->json('data.id');

        $order = Order::query()->findOrFail($id);
        $this->assertEquals(5000, $order->amount);
        $this->assertEquals(4500, $order->total_amount);
        $this->assertEquals(500, $order->paid_amount);
        $this->assertEquals(1000, $order->weekly_payment);
        $this->assertSame('At the shop', $order->notes);
    }

    /**
     * EN: An empty cart, or a rule the checkout refuses (a down payment on a cash sale), comes back as a field error and nothing is saved.
     * AR: السلة الفارغة، أو قاعدة يرفضها الـ checkout (عربون على بيع كاش)، ترجع خطأ على الحقل وما ينحفظ شي.
     */
    public function test_checkout_errors_reach_the_form(): void
    {
        $item = $this->stockedItem();
        $customer = Customer::factory()->create();
        $this->actingAs($this->superUser());

        $this->postJson('/admin-api/orders', ['customer_id' => $customer->id, 'payment_type' => 'cash', 'date' => '2026-09-10', 'lines' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines');

        $this->postJson('/admin-api/orders', [
            'customer_id' => $customer->id,
            'payment_type' => 'cash',
            'date' => '2026-09-10',
            'down_payment' => 100,
            'lines' => [['item_id' => $item->id, 'packets' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('down_payment');

        $this->assertSame(0, Order::query()->count());
    }

    /**
     * EN: Adding an order is for super users only, as in the panel.
     * AR: إضافة أوردر بس للسوبر يوزر، مثل اللوحة.
     */
    public function test_adding_an_order_needs_a_super_user(): void
    {
        $this->actingAs($this->userWith('order.view', 'order.update'))
            ->postJson('/admin-api/orders', ['lines' => []])
            ->assertForbidden();
    }

    /**
     * EN: The edit form starts with the order's lines and discount, and saving it goes through UpdateOrder; an order with payments
     *     keeps its customer, and changing it is refused with an error on the customer.
     * AR: فورم التعديل يبدي ببنود الأوردر وخصمه، وحفظه يمر من UpdateOrder؛ والأوردر اللي عليه تسديدات يبقى على زبونه،
     *     وتغييره ينرفض بخطأ على الزبون.
     */
    public function test_the_edit_form_and_its_save(): void
    {
        $item = $this->stockedItem(salePrice: 1000);
        $customer = Customer::factory()->create(['name' => 'Ali']);
        $this->actingAs($this->superUser());
        $id = $this->postJson('/admin-api/orders', [
            'customer_id' => $customer->id,
            'payment_type' => 'postpaid',
            'date' => '2026-09-10',
            'down_payment' => 500,
            'discount' => 200,
            'lines' => [['item_id' => $item->id, 'packets' => 3]],
        ])->assertCreated()->json('data.id');

        $form = $this->getJson("/admin-api/orders/{$id}/edit")
            ->assertOk()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.customer_name', 'Ali')
            ->assertJsonPath('data.has_payments', true)
            ->assertJsonPath('data.discount', 200)
            ->assertJsonPath('data.lines.0.packets', 3)
            ->json('data');

        $this->putJson("/admin-api/orders/{$id}", [
            ...$form,
            'lines' => [['item_id' => $item->id, 'lens_power_id' => null, 'colors' => [], 'packets' => 4]],
        ])->assertOk();
        $this->assertEquals(3800, Order::query()->findOrFail($id)->total_amount);

        $this->putJson("/admin-api/orders/{$id}", [
            ...$form,
            'customer_id' => Customer::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    }

    /**
     * EN: An order with payments on it cannot be deleted; one without payments is deleted and shows only with the deleted filter.
     * AR: الأوردر اللي عليه تسديدات ما ينحذف؛ واللي ما عليه ينحذف ويطلع بس ويا فلتر المحذوفات.
     */
    public function test_delete_an_order(): void
    {
        $customer = Customer::factory()->create();
        $paid = $this->order($customer, 1000);
        (new PayCustomerDebt)->payOrders($this->superUser(), $customer, [$paid->id => 100], '2026-09-05');
        $unpaid = $this->order($customer, 500);
        $this->actingAs($this->superUser());

        $this->deleteJson("/admin-api/orders/{$paid->id}")->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->deleteJson("/admin-api/orders/{$unpaid->id}")->assertNoContent();

        $this->getJson('/admin-api/orders?trashed=only')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unpaid->id);
        $this->getJson("/admin-api/orders/{$unpaid->id}")->assertOk()->assertJsonPath('data.print', null)->assertJsonPath('data.can.update', false);
    }

    /**
     * EN: The orders, customers, and payments lists read the same number of queries for many rows as for a few.
     * AR: قوائم الأوردرات والزبائن والتسديدات تقرا نفس عدد الاستعلامات للصفوف الهواية مثل القليلة.
     */
    public function test_the_lists_do_not_query_once_per_row(): void
    {
        $this->actingAs($this->userWith('order.view', 'customer.view', 'customer_payment.view', 'customer_payment.create', 'item.view_cost_price', 'invoice_printing.view'));
        $counts = [];

        foreach ([2, 6] as $rows) {
            for ($index = 0; $index < $rows; $index++) {
                $customer = Customer::factory()->create();
                $order = $this->order($customer, 1000);
                (new PayCustomerDebt)->payOrders($this->superUser(), $customer, [$order->id => 100], '2026-09-05');
            }

            foreach (['/admin-api/orders', '/admin-api/customers', '/admin-api/customer-payments', '/admin-api/invoice-printing'] as $endpoint) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $this->getJson($endpoint)->assertOk();
                $counts[$endpoint][] = count(DB::getQueryLog());
                DB::disableQueryLog();
            }
        }

        foreach ($counts as $endpoint => [$few, $many]) {
            $this->assertSame($few, $many, $endpoint);
        }
    }
}
