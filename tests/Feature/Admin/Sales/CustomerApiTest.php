<?php

namespace Tests\Feature\Admin\Sales;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Orders\PayCustomerDebt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CustomerApiTest extends SalesTestCase
{
    /**
     * EN: The customers list is searched by name or phone and filtered by the payment type and invoice by invoice,
     *     with each customer's debt and whether it can be paid.
     * AR: قائمة الزبائن تنبحث بالاسم أو التلفون وتنفلتر بنوع الدفع والفاتورة بفاتورة،
     *     ويا دين كل زبون وإذا ينگدر يتسدد.
     */
    public function test_the_list_with_debts_search_and_filters(): void
    {
        $ali = Customer::factory()->create(['name' => 'Ali', 'phone' => '0770', 'payment_type' => 'postpaid', 'is_invoice_by_invoice' => true]);
        $this->order($ali, 1000);
        $this->order($ali, 500, OrderPaymentType::Cash);
        Customer::factory()->create(['name' => 'Alia', 'phone' => '0780', 'payment_type' => 'cash', 'is_invoice_by_invoice' => false]);
        Customer::factory()->create(['name' => 'Omar', 'phone' => '0771', 'payment_type' => 'postpaid', 'is_invoice_by_invoice' => false]);

        $this->actingAs($this->userWith('customer.view', 'customer_payment.create', 'invoice.create'));

        $response = $this->getJson('/admin-api/customers?search=ali&sort=name')->assertOk();
        $this->assertSame(['Ali', 'Alia'], array_column($response->json('data'), 'name'));
        $this->assertEquals(1000, $response->json('data.0.debt'));
        $this->assertTrue($response->json('data.0.can.pay'));
        $this->assertFalse($response->json('data.1.can.pay'));

        $this->getJson('/admin-api/customers?search=077')->assertJsonCount(2, 'data');
        $this->getJson('/admin-api/customers?filter[payment_type]=cash')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Alia');
        $this->getJson('/admin-api/customers?filter[is_invoice_by_invoice]=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ali');
    }

    /**
     * EN: A customer is created with its contract file and edited; a name and a payment type are required.
     * AR: الزبون ينضاف ويا ملف عقده وينعدل؛ والاسم ونوع الدفع مطلوبين.
     */
    public function test_create_and_update_a_customer(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWith('customer.view', 'customer.create', 'customer.update'));

        $this->postJson('/admin-api/customers', ['phone' => '0770'])->assertJsonValidationErrors(['name', 'payment_type']);

        $id = $this->post('/admin-api/customers', [
            'name' => 'Ali',
            'payment_type' => 'postpaid',
            'phone' => '0770',
            'email' => 'ali@example.com',
            'is_invoice_by_invoice' => '1',
            'contract' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $customer = Customer::query()->findOrFail($id);
        $this->assertTrue($customer->is_invoice_by_invoice);
        Storage::disk('public')->assertExists($customer->contract);

        $this->post("/admin-api/customers/{$id}", ['name' => 'Ali Hassan', 'payment_type' => 'cash', 'is_invoice_by_invoice' => '0'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ali Hassan')
            ->assertJsonPath('data.payment_type.value', 'cash')
            ->assertJsonPath('data.is_invoice_by_invoice', false);
    }

    /**
     * EN: A customer's page has its eight total cards, each read with its number and info; the days since cards show the date beside them.
     * AR: صفحة الزبون بيها ثمان بطاقات مجاميع، كل وحدة تنقرا برقمها وشرحها؛ وبطاقات الأيام من آخر شي تبين التاريخ يمها.
     */
    public function test_the_customer_page_and_its_cards(): void
    {
        $customer = Customer::factory()->create(['name' => 'Ali']);
        $this->order($customer, 1000, date: '2026-09-01');
        $this->actingAs($this->userWith('customer.view'));

        $this->getJson("/admin-api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Ali')
            ->assertJsonPath('data.debt', 1000)
            ->assertJsonPath('metrics', ['debt', 'paid', 'last_payment', 'sales', 'orders_count', 'average_order', 'items_count', 'last_order']);

        $this->getJson("/admin-api/customers/{$customer->id}/metrics/debt")
            ->assertOk()
            ->assertJsonPath('value.raw', 1000)
            ->assertJsonStructure(['label', 'value' => ['number', 'currency'], 'explanation', 'example']);

        $this->assertStringContainsString('2026/09/01', $this->getJson("/admin-api/customers/{$customer->id}/metrics/last_order")->json('value.currency'));
        $this->assertSame('—', $this->getJson("/admin-api/customers/{$customer->id}/metrics/last_payment")->json('value.number'));
        $this->getJson("/admin-api/customers/{$customer->id}/metrics/nope")->assertNotFound();
    }

    /**
     * EN: A customer's payments are listed on its page, newest first, and a deleted one shows with the deleted filter.
     * AR: تسديدات الزبون تنعرض بصفحته، الأحدث أول، والمحذوف يطلع ويا فلتر المحذوفات.
     */
    public function test_the_customer_payments_list(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order($customer, 1000);
        $actor = $this->superUser();
        $first = (new PayCustomerDebt)->payOrders($actor, $customer, [$order->id => 100], '2026-09-02');
        $second = (new PayCustomerDebt)->payOrders($actor, $customer, [$order->id => 200], '2026-09-03');
        $first->delete();
        $this->actingAs($this->userWith('customer.view', 'customer_payment.view'));

        $this->getJson("/admin-api/customers/{$customer->id}/payments")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson("/admin-api/customers/{$customer->id}/payments?trashed=with")->assertJsonCount(2, 'data');
    }

    /**
     * EN: A customer is deleted and restored by a user with the permissions, and without the view permission nothing is shown.
     * AR: الزبون ينحذف ويرجع باليوزر اللي عنده الصلاحيات، وبدون صلاحية العرض ما يطلع شي.
     */
    public function test_delete_restore_and_permissions(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWith())->getJson('/admin-api/customers')->assertForbidden();

        $this->actingAs($this->userWith('customer.view', 'customer.delete', 'customer.restore'));
        $this->deleteJson("/admin-api/customers/{$customer->id}")->assertNoContent();
        $this->assertSoftDeleted($customer);
        $this->postJson("/admin-api/customers/{$customer->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($customer);
    }
}
