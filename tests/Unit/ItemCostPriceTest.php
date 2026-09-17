<?php

namespace Tests\Unit;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCostPriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A new item starts with a cost price of zero.
     * AR: المادة الجديدة تبدي بسعر تكلفة صفر.
     */
    public function test_new_item_starts_with_a_zero_cost_price(): void
    {
        $item = Item::create(['name' => 'Round Glasses', 'image' => 'items/round.png']);

        $this->assertSame(0.0, $item->fresh()->cost_price);
    }

    /**
     * EN: The cost price cannot be entered when an item is created; the entered value is ignored.
     * AR: سعر التكلفة ما ينكتب وقت إنشاء المادة؛ القيمة المدخلة تنتجاهل.
     */
    public function test_cost_price_cannot_be_entered_when_creating_an_item(): void
    {
        $item = Item::create(['name' => 'Round Glasses', 'image' => 'items/round.png', 'cost_price' => 500]);

        $this->assertSame(0.0, $item->fresh()->cost_price);
    }

    /**
     * EN: The cost price cannot be edited when an item is updated; the entered value is ignored.
     * AR: سعر التكلفة ما ينعدل وقت تعديل المادة؛ القيمة المدخلة تنتجاهل.
     */
    public function test_cost_price_cannot_be_edited_when_updating_an_item(): void
    {
        $item = Item::create(['name' => 'Round Glasses', 'image' => 'items/round.png']);

        $item->update(['name' => 'Round Glasses 2', 'cost_price' => 900]);

        $item = $item->fresh();

        $this->assertSame('Round Glasses 2', $item->name);
        $this->assertSame(0.0, $item->cost_price);
    }
}
