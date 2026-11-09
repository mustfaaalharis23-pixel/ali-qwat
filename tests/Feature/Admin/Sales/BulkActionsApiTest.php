<?php

namespace Tests\Feature\Admin\Sales;

use App\Models\Customer;
use App\Orders\PayCustomerDebt;

class BulkActionsApiTest extends SalesTestCase
{
    /**
     * EN: The orders list deletes the chosen orders together (the panel's delete bulk action); an order with payments on it is skipped and counted.
     * AR: قائمة الأوردرات تحذف الأوردرات المختارة سوه (حذف جماعي مثل اللوحة)؛ والأوردر اللي عليه تسديدات يتعدّى وينحسب.
     */
    public function test_orders_are_deleted_together(): void
    {
        $customer = Customer::factory()->create();
        $first = $this->order($customer, 500);
        $second = $this->order($customer, 700);
        $paid = $this->order($customer, 1000);
        (new PayCustomerDebt)->payOrders($this->superUser(), $customer, [$paid->id => 100], '2026-09-05');
        $this->actingAs($this->superUser());

        $this->postJson('/admin-api/orders/bulk', ['action' => 'delete', 'ids' => [$first->id, $second->id, $paid->id]])
            ->assertOk()
            ->assertJson(['count' => 2, 'skipped' => 1]);

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertNotSoftDeleted($paid);
    }

    /**
     * EN: Deleting orders together needs the delete ability on every order (a super user, as OrderPolicy says); otherwise nothing is deleted.
     * AR: حذف الأوردرات سوه يحتاج صلاحية الحذف على كل أوردر (يوزر سوبر حسب OrderPolicy)؛ وإلا ما ينحذف ولا واحد.
     */
    public function test_deleting_orders_together_needs_the_ability(): void
    {
        $order = $this->order(Customer::factory()->create());
        $this->actingAs($this->userWith('order.view', 'order.update'));

        $this->postJson('/admin-api/orders/bulk', ['action' => 'delete', 'ids' => [$order->id]])->assertForbidden();
        $this->assertNotSoftDeleted($order);

        $this->actingAs($this->superUser());
        $this->postJson('/admin-api/orders/bulk', ['action' => 'restore', 'ids' => [$order->id]])->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->postJson('/admin-api/orders/bulk', ['action' => 'delete', 'ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    /**
     * EN: The customers list deletes, restores, and deletes forever the chosen customers together, each counted;
     *     a record not in the state the action needs is left as it is.
     * AR: قائمة الزبائن تحذف وترجّع وتحذف نهائياً الزبائن المختارين سوه، وكل وحدة تنحسب؛
     *     والسجل اللي مو بالحالة اللي يحتاجها الإجراء يبقى مثل ما هو.
     */
    public function test_customers_are_deleted_restored_and_force_deleted_together(): void
    {
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();
        $this->actingAs($this->userWith('customer.view', 'customer.delete', 'customer.restore'));

        $this->postJson('/admin-api/customers/bulk', ['action' => 'delete', 'ids' => [$first->id, $second->id]])->assertOk()->assertJson(['count' => 2]);
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);

        $this->postJson('/admin-api/customers/bulk', ['action' => 'restore', 'ids' => [$first->id]])->assertOk()->assertJson(['count' => 1]);
        $this->assertNotSoftDeleted($first);

        $this->postJson('/admin-api/customers/bulk', ['action' => 'force_delete', 'ids' => [$second->id]])->assertForbidden();
        $this->assertSoftDeleted($second);

        $this->actingAs($this->superUser());
        $this->postJson('/admin-api/customers/bulk', ['action' => 'force_delete', 'ids' => [$first->id, $second->id]])->assertOk()->assertJson(['count' => 2]);
        $this->assertDatabaseMissing('customers', ['id' => $first->id]);
        $this->assertDatabaseMissing('customers', ['id' => $second->id]);
    }

    /**
     * EN: A bulk action on customers checks every chosen customer's policy before changing any; without the permission nothing changes.
     * AR: الإجراء الجماعي على الزبائن يفحص صلاحية كل زبون مختار قبل ما يغيّر أي واحد؛ وبدون الصلاحية ما يتغير شي.
     */
    public function test_customer_bulk_actions_need_the_permission(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->userWith('customer.view'));

        $this->postJson('/admin-api/customers/bulk', ['action' => 'delete', 'ids' => [$customer->id]])->assertForbidden();
        $this->assertNotSoftDeleted($customer);

        $this->actingAs($this->userWith('customer.view', 'customer.delete'));
        $customer->delete();
        $this->postJson('/admin-api/customers/bulk', ['action' => 'restore', 'ids' => [$customer->id]])->assertForbidden();
        $this->assertSoftDeleted($customer);
    }

    /**
     * EN: The lists and the sales abilities say whether the user may use each bulk action, so the page shows only those.
     * AR: القوائم وصلاحيات المبيعات تگول إذا اليوزر يگدر يستخدم كل إجراء جماعي، حتى الصفحة تطلع بس هذني.
     */
    public function test_the_lists_carry_the_bulk_abilities(): void
    {
        $this->actingAs($this->userWith('customer.view', 'customer.delete', 'order.view'));

        $this->getJson('/admin-api/customers')->assertOk()
            ->assertJsonPath('can.delete_any', true)
            ->assertJsonPath('can.restore_any', false)
            ->assertJsonPath('can.force_delete_any', false);
        $this->getJson('/admin-api/orders')->assertOk()->assertJsonPath('can.delete_any', false);
        $this->getJson('/admin-api/sales/abilities')->assertOk()
            ->assertJsonPath('delete_customers', true)
            ->assertJsonPath('restore_customers', false)
            ->assertJsonPath('force_delete_customers', false)
            ->assertJsonPath('delete_orders', false);

        $this->actingAs($this->superUser());
        $this->getJson('/admin-api/orders')->assertJsonPath('can.delete_any', true);
        $this->getJson('/admin-api/customers')->assertJsonPath('can.force_delete_any', true);
        $this->getJson('/admin-api/sales/abilities')->assertJsonPath('delete_orders', true)->assertJsonPath('force_delete_customers', true);
    }
}
