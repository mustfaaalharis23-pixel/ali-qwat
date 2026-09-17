<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: An item's total price is its price times its quantity, calculated on save; a given total price is ignored.
     * AR: السعر الكلي للبند = السعر × الكمية، ينحسب وقت الحفظ، وأي سعر كلي ينطى يدوياً ينهمل.
     */
    public function test_total_price_is_price_times_quantity(): void
    {
        $line = OrderItem::factory()->create([
            'quantity' => 2.5,
            'price' => 1500,
            'total_price' => 99,
        ])->fresh();

        $this->assertEquals(3750, $line->total_price);
    }

    /**
     * EN: Values that are not more than zero.
     * AR: قيم مو أكبر من صفر.
     *
     * @return array<string, array{float}>
     */
    public static function notPositiveValues(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    /**
     * EN: A sold line keeps the item's cost price of the moment it was sold, and its cost and profit come from it.
     * AR: البند المبيع يحتفظ بسعر تكلفة المادة لحظة بيعها، وكلفته وربحه ينحسبون منه.
     */
    public function test_sold_line_keeps_the_cost_price_of_the_moment_it_was_sold(): void
    {
        $item = Item::factory()->stocked(10, 700)->create();

        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 2, 'price' => 1000])->fresh();

        $this->assertEquals(700, $line->cost_price);
        $this->assertEquals(1400, $line->total_cost);
        $this->assertEquals(600, $line->profit);
    }

    /**
     * EN: A later purchase changes the item's cost but never the cost or the profit of a line already sold.
     * AR: الشراء اللاحق يغيّر كلفة المادة، بس ما يغيّر كلفة ولا ربح بند انباع من قبل.
     */
    public function test_a_later_purchase_does_not_change_a_sold_line(): void
    {
        $item = Item::factory()->stocked(10, 700)->create();
        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 2, 'price' => 1000]);

        $this->buyAgain($item, quantity: 10, unitCost: 900);

        $this->assertEquals(900, $item->fresh()->cost_price);
        $this->assertEquals(700, $line->fresh()->cost_price);
        $this->assertEquals(600, $line->fresh()->profit);
    }

    /**
     * EN: Changing the quantity of a sold line recalculates its cost and its profit with the cost price it kept.
     * AR: تغيير كمية بند مبيع يعيد حساب كلفته وربحه بسعر التكلفة المحفوظ عليه.
     */
    public function test_changing_the_quantity_recalculates_the_cost_and_the_profit(): void
    {
        $item = Item::factory()->stocked(10, 700)->create();
        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 2, 'price' => 1000]);

        $line->update(['quantity' => 3]);
        $line = $line->fresh();

        $this->assertEquals(2100, $line->total_cost);
        $this->assertEquals(900, $line->profit);
    }

    /**
     * EN: Buys the item again on a new purchase invoice both sides confirmed, so its cost price changes.
     * AR: يشتري المادة مرة ثانية بفاتورة شراء مؤكدة من الطرفين، فتتغير كلفتها.
     */
    private function buyAgain(Item $item, float $quantity, float $unitCost): void
    {
        InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 1,
            'box_quantity' => $quantity,
            'price' => $quantity * $unitCost,
        ]);
    }

    /**
     * EN: An item is not sold for more than its available quantity.
     * AR: المادة ما تنباع بأكثر من كميتها المتوفرة.
     */
    public function test_selling_more_than_the_available_quantity_is_refused(): void
    {
        $item = Item::factory()->stocked(10)->create();

        $this->expectException(ValidationException::class);

        OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 11, 'price' => 1000]);
    }

    /**
     * EN: Raising the quantity of a sold line counts the line's own quantity as available, and stops at what is left.
     * AR: زيادة كمية بند مبيع تحسب كمية البند نفسه متوفرة، وتوقف عند اللي باقي.
     */
    public function test_raising_the_quantity_of_a_sold_line_stops_at_what_is_left(): void
    {
        $item = Item::factory()->stocked(10)->create();
        $line = OrderItem::factory()->create(['item_id' => $item->id, 'quantity' => 4, 'price' => 1000]);

        $line->update(['quantity' => 10]);

        $this->assertEquals(0, $item->fresh()->available_quantity);

        $this->expectException(ValidationException::class);

        $line->update(['quantity' => 11]);
    }

    /**
     * EN: An item's quantity must be more than zero.
     * AR: كمية البند لازم تكون أكبر من صفر.
     */
    #[DataProvider('notPositiveValues')]
    public function test_quantity_must_be_more_than_zero(float $quantity): void
    {
        $this->expectException(ValidationException::class);

        OrderItem::factory()->create(['quantity' => $quantity, 'price' => 1000]);
    }

    /**
     * EN: An item's price must be more than zero.
     * AR: سعر البند لازم يكون أكبر من صفر.
     */
    #[DataProvider('notPositiveValues')]
    public function test_price_must_be_more_than_zero(float $price): void
    {
        $this->expectException(ValidationException::class);

        OrderItem::factory()->create(['quantity' => 1, 'price' => $price]);
    }
}
