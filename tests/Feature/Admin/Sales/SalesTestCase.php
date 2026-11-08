<?php

namespace Tests\Feature\Admin\Sales;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What the tests of the sales screens of the admin application share: a user with permissions, a super user,
 * an item in stock sold by the packet, and a saved order.
 */
abstract class SalesTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Creates an active user with the given permissions.
     * AR: ينشئ يوزر فعّال بالصلاحيات المعطاة.
     */
    protected function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);
        }

        return $user;
    }

    /**
     * EN: Creates an active super user.
     * AR: ينشئ يوزر سوبر فعّال.
     */
    protected function superUser(): User
    {
        return User::factory()->create(['is_active' => true, 'is_super' => true]);
    }

    /**
     * EN: Creates an item in stock, packed 12 pieces to a packet (a packet is a dozen), at the given price of a dozen.
     * AR: ينشئ مادة بالمخزون، الباكيت بيه 12 قطعة (يعني درزن)، بسعر الدرزن المعطى.
     */
    protected function stockedItem(string $name = 'Glasses', float $salePrice = 1000, float $dozens = 10): Item
    {
        return Item::factory()->stocked($dozens)->create(['name' => $name, 'sale_price' => $salePrice, 'pieces_per_packet' => 12]);
    }

    /**
     * EN: Creates a saved order of the customer with one line of the given amount.
     * AR: ينشئ أوردر محفوظ للزبون ببند واحد بالمبلغ المعطى.
     */
    protected function order(Customer $customer, float $amount = 1000, OrderPaymentType $paymentType = OrderPaymentType::Postpaid, string $date = '2026-09-01', array $attributes = []): Order
    {
        $order = Order::factory()->create(['customer_id' => $customer->id, 'payment_type' => $paymentType, 'date' => $date, ...$attributes]);

        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'price' => $amount]);

        return $order->fresh();
    }
}
