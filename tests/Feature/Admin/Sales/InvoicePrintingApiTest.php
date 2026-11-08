<?php

namespace Tests\Feature\Admin\Sales;

use App\Enums\OrderPaymentType;
use App\Models\Customer;

class InvoicePrintingApiTest extends SalesTestCase
{
    /**
     * EN: The invoice printing list has every order, newest first, filtered by the period and the payment type, each with its two
     *     print links; the cost, the profit, and the margin stay hidden from a user who cannot see the cost.
     * AR: قائمة طباعة الفواتير بيها كل الأوردرات، الأحدث أول، وتنفلتر بالفترة ونوع الدفع، وكل وحدة ويا رابطين الطباعة؛
     *     والكلفة والربح والهامش مخفية عن اليوزر اللي ما يگدر يشوف الكلفة.
     */
    public function test_the_list_with_print_links_and_filters(): void
    {
        $customer = Customer::factory()->create();
        $august = $this->order($customer, 1000, OrderPaymentType::Cash, '2026-08-10');
        $september = $this->order($customer, 2000, OrderPaymentType::Postpaid, '2026-09-10');

        $response = $this->actingAs($this->userWith('invoice_printing.view'))->getJson('/admin-api/invoice-printing')->assertOk();

        $this->assertSame([$september->id, $august->id], array_column($response->json('data'), 'id'));
        $this->assertSame(route('invoices.print', ['order' => $september, 'format' => 'a4']), $response->json('data.0.print.a4'));
        $this->assertSame(route('invoices.print', ['order' => $september, 'format' => 'pos']), $response->json('data.0.print.pos'));
        $this->assertNull($response->json('data.0.profit_margin_percentage'));

        $this->getJson('/admin-api/invoice-printing?filter[from]=2026-09-01')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $september->id);
        $this->getJson('/admin-api/invoice-printing?filter[payment_type]=cash')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $august->id);
    }

    /**
     * EN: A user who can see the cost gets the margin of each order: its profit over what it was sold for, in percent.
     * AR: اليوزر اللي يگدر يشوف الكلفة ياخذ هامش كل أوردر: ربحه على سعر بيعه، بالنسبة المئوية.
     */
    public function test_the_margin_shows_to_a_user_who_can_see_the_cost(): void
    {
        $order = $this->order(Customer::factory()->create(), 1000);

        $response = $this->actingAs($this->userWith('invoice_printing.view', 'item.view_cost_price'))->getJson('/admin-api/invoice-printing')->assertOk();

        $this->assertEquals(round($order->profit / 1000 * 100, 1), $response->json('data.0.profit_margin_percentage'));
    }

    /**
     * EN: Without the invoice printing permission the page is forbidden.
     * AR: بدون صلاحية طباعة الفواتير الصفحة ممنوعة.
     */
    public function test_the_page_needs_its_permission(): void
    {
        $this->actingAs($this->userWith('order.view'))->getJson('/admin-api/invoice-printing')->assertForbidden();
    }
}
