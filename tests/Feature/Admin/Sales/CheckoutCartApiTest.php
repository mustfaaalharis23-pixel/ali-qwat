<?php

namespace Tests\Feature\Admin\Sales;

use App\Models\Customer;
use App\Models\Item;

class CheckoutCartApiTest extends SalesTestCase
{
    /**
     * EN: The items of the cart are searched by name: only active items sold by the packet, those in stock first, each with its price,
     *     its packets left, and the packets of it already in the cart.
     * AR: مواد السلة تنبحث بالاسم: بس المواد الفعّالة اللي تنباع بالباكيت، اللي بالمخزون أول، وكل وحدة بسعرها
     *     وباكيتاتها الباقية والباكيتات اللي منها بالسلة.
     */
    public function test_item_cards_are_searched_with_their_stock_and_cart(): void
    {
        $inStock = $this->stockedItem('Glasses A', dozens: 10);
        Item::factory()->create(['name' => 'Glasses B', 'sale_price' => 1000, 'pieces_per_packet' => 12]);
        Item::factory()->inactive()->stocked(5)->create(['name' => 'Glasses C', 'pieces_per_packet' => 12]);
        $this->stockedItem('Frame');

        $response = $this->actingAs($this->superUser())->postJson('/admin-api/orders/checkout', [
            'search' => 'glasses',
            'cart' => [['item_id' => $inStock->id, 'lens_power_id' => null, 'colors' => [], 'packets' => 2]],
        ])->assertOk();

        $this->assertSame(['Glasses A', 'Glasses B'], array_column($response->json('items'), 'name'));
        $this->assertSame(10, $response->json('items.0.packetsLeft'));
        $this->assertSame(2, $response->json('items.0.inCart'));
        $this->assertTrue($response->json('items.1.isOutOfStock'));
    }

    /**
     * EN: The cart summary prices each line at its item's price of a dozen, and a percent discount follows the total, to a whole dinar;
     *     each line says how many packets are left of its type.
     * AR: ملخص السلة يسعّر كل بند بسعر درزن مادته، وخصم النسبة يمشي ويا المجموع، لأقرب دينار؛
     *     وكل بند يگول شگد باقي باكيتات من نوعه.
     */
    public function test_the_cart_summary_with_a_percent_discount(): void
    {
        $item = $this->stockedItem(salePrice: 1500, dozens: 10);

        $this->actingAs($this->superUser())->postJson('/admin-api/orders/checkout', [
            'cart' => [['item_id' => $item->id, 'lens_power_id' => null, 'colors' => [], 'packets' => 3]],
            'discount_percent' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('summary.subtotal', 4500)
            ->assertJsonPath('summary.discount', 450)
            ->assertJsonPath('summary.total', 4050)
            ->assertJsonPath('summary.lines.0.packets', 3)
            ->assertJsonPath('summary.lines.0.packetsLeft', 10);
    }

    /**
     * EN: On the edit of an order, its own packets count as available to it, and its lines keep the price they were sold at.
     * AR: بتعديل الأوردر، باكيتاته نفسها تنحسب متوفرة إله، وبنوده تبقى بالسعر اللي انباعت بيه.
     */
    public function test_the_edit_counts_its_own_packets_and_keeps_its_prices(): void
    {
        $item = $this->stockedItem(salePrice: 1000, dozens: 10);
        $this->actingAs($this->superUser());
        $id = $this->postJson('/admin-api/orders', [
            'customer_id' => Customer::factory()->create()->id,
            'payment_type' => 'cash',
            'date' => '2026-09-10',
            'lines' => [['item_id' => $item->id, 'packets' => 4]],
        ])->assertCreated()->json('data.id');
        $item->update(['sale_price' => 2000]);

        $this->postJson('/admin-api/orders/checkout', [
            'order_id' => $id,
            'cart' => [['item_id' => $item->id, 'lens_power_id' => null, 'colors' => [], 'packets' => 4]],
        ])
            ->assertOk()
            ->assertJsonPath('items.0.packetsLeft', 10)
            ->assertJsonPath('summary.subtotal', 4000);
    }

    /**
     * EN: The cart needs the permission to add or edit an order.
     * AR: السلة تحتاج صلاحية إضافة أو تعديل أوردر.
     */
    public function test_the_cart_needs_a_permission(): void
    {
        $this->actingAs($this->userWith('order.view'))->postJson('/admin-api/orders/checkout', ['cart' => []])->assertForbidden();
    }
}
