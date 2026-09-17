<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The order's amount and total amount are the sum of its items' total prices.
     * AR: مبلغ الأوردر ومبلغه الكلي = مجموع السعر الكلي لبنوده.
     */
    public function test_order_amounts_are_the_sum_of_its_items(): void
    {
        $order = Order::factory()->create();
        $this->orderItem($order, quantity: 2, price: 1500);
        $this->orderItem($order, quantity: 3, price: 250);

        $order = $order->fresh();

        $this->assertEquals(3750, $order->amount);
        $this->assertEquals(3750, $order->total_amount);
    }

    /**
     * EN: Changing an item's quantity or price recalculates the order's amounts.
     * AR: تغيير كمية البند أو سعره يعيد حساب مبالغ الأوردر.
     */
    public function test_changing_an_item_recalculates_the_order(): void
    {
        $order = Order::factory()->create();
        $item = $this->orderItem($order, quantity: 2, price: 1000);

        $item->update(['quantity' => 5]);
        $this->assertEquals(5000, $order->fresh()->total_amount);

        $item->update(['price' => 1200]);
        $order = $order->fresh();

        $this->assertEquals(6000, $order->amount);
        $this->assertEquals(6000, $order->total_amount);
    }

    /**
     * EN: Deleting an item recalculates the order's amounts.
     * AR: حذف بند يعيد حساب مبالغ الأوردر.
     */
    public function test_deleting_an_item_recalculates_the_order(): void
    {
        $order = Order::factory()->create();
        $this->orderItem($order, quantity: 1, price: 1000);
        $item = $this->orderItem($order, quantity: 1, price: 500);

        $item->delete();
        $order = $order->fresh();

        $this->assertEquals(1000, $order->amount);
        $this->assertEquals(1000, $order->total_amount);
    }

    /**
     * EN: Deleting an order soft deletes the order and all of its items.
     * AR: حذف الأوردر يسوي soft delete للأوردر ولكل بنوده.
     */
    public function test_deleting_an_order_soft_deletes_it_and_its_items(): void
    {
        $order = Order::factory()->create();
        $first = $this->orderItem($order, quantity: 1, price: 1000);
        $second = $this->orderItem($order, quantity: 2, price: 500);

        $order->delete();

        $this->assertSoftDeleted($order);
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    }

    /**
     * EN: The last item of an order cannot be deleted alone, because there is no order without items.
     * AR: آخر بند بالأوردر ما ينحذف لوحده، لأن ماكو أوردر بدون بنود.
     */
    public function test_last_item_of_an_order_cannot_be_deleted(): void
    {
        $order = Order::factory()->create();
        $item = $this->orderItem($order, quantity: 1, price: 1000);

        $this->expectException(ValidationException::class);

        $item->delete();
    }

    /**
     * EN: The order's cost and profit are the sum of its items' costs and profits.
     * AR: كلفة الأوردر وربحه = مجموع كلف بنوده وأرباحهن.
     */
    public function test_order_cost_and_profit_are_the_sum_of_its_items(): void
    {
        $order = Order::factory()->create();
        $this->orderItem($order, quantity: 2, price: 1500, unitCost: 1000);
        $this->orderItem($order, quantity: 3, price: 250, unitCost: 100);

        $order = $order->fresh();

        $this->assertEquals(2300, $order->total_cost);
        $this->assertEquals(1450, $order->profit);
    }

    /**
     * EN: Deleting an item recalculates the order's cost and profit too.
     * AR: حذف بند يعيد حساب كلفة الأوردر وربحه هم.
     */
    public function test_deleting_an_item_recalculates_the_cost_and_the_profit(): void
    {
        $order = Order::factory()->create();
        $this->orderItem($order, quantity: 2, price: 1500, unitCost: 1000);
        $line = $this->orderItem($order, quantity: 3, price: 250, unitCost: 100);

        $line->delete();
        $order = $order->fresh();

        $this->assertEquals(2000, $order->total_cost);
        $this->assertEquals(1000, $order->profit);
    }

    /**
     * EN: Creates an item line on the order for a new item with the given quantity and price, bought at the given unit cost.
     * AR: ينشئ بند على الأوردر لمادة جديدة بالكمية والسعر المحددين، مشتراة بكلفة الوحدة المحددة.
     */
    private function orderItem(Order $order, float $quantity, float $price, float $unitCost = 100): OrderItem
    {
        return OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_id' => Item::factory()->stocked(1000, $unitCost)->create()->id,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }
}
