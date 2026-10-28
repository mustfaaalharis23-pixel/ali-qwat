<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use App\Orders\UpdateOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The discount is one amount off the whole order. It is spread over the order's lines by each line's share of the order,
 * so the profit of every item stays true. In the examples every item is sold in packets of 12 pieces (one dozen) at 1,500
 * a dozen, and a dozen cost us 100: 10 packets are 15,000 and cost 1,000, 5 packets are 7,500 and cost 500.
 */
class OrderDiscountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: An order of 10 packets (15,000) and 5 packets (7,500) is sold in cash with a discount of 4,500 on the order.
     *     The order is 22,500 before the discount and 18,000 after it, the cash order is paid 18,000, and nothing remains on it.
     * AR: أوردر بيه 10 باكيتات (15,000) و5 باكيتات (7,500) انباع كاش بخصم 4,500 على الأوردر.
     *     الأوردر 22,500 قبل الخصم و18,000 بعده، والأوردر الكاش مدفوع 18,000، وما باقي عليه شي.
     */
    public function test_the_discount_comes_off_the_order_total(): void
    {
        $order = $this->sell($this->twoLines(), discount: 4500);

        $this->assertEquals(22500, $order->amount);
        $this->assertEquals(18000, $order->total_amount);
        $this->assertEquals(18000, $order->paid_amount);
        $this->assertEquals(0, $order->remaining);
    }

    /**
     * EN: The discount of 4,500 is spread over the lines by their share of the order: the line of 15,000 is two thirds of it and
     *     takes 3,000, the line of 7,500 is one third and takes 1,500. Each line keeps its own total before the discount.
     * AR: خصم 4,500 يتوزع على البنود حسب حصتها من الأوردر: بند الـ 15,000 ثلثين الأوردر وياخذ 3,000،
     *     وبند الـ 7,500 ثلث وياخذ 1,500. كل بند يبقى مجموعه قبل الخصم مثل ما هو.
     */
    public function test_the_discount_is_spread_over_the_lines_by_their_share(): void
    {
        $order = $this->sell($this->twoLines(), discount: 4500);

        $lines = $order->items()->orderBy('id')->get();
        $this->assertEquals([15000, 7500], $lines->pluck('total_price')->all());
        $this->assertEquals([3000, 1500], $lines->pluck('discount')->all());
    }

    /**
     * EN: The shares are whole dinars and always add up to the discount: 10,000 over three lines of 15,000 each is
     *     3,333 + 3,333 + 3,334, and the order becomes 35,000.
     * AR: الحصص بالدينار الكامل ومجموعها دائماً نفس الخصم: 10,000 على ثلاث بنود كل واحد 15,000 تصير
     *     3,333 + 3,333 + 3,334، والأوردر يصير 35,000.
     */
    public function test_the_shares_are_whole_dinars_that_add_up_to_the_discount(): void
    {
        $order = $this->sell([
            new CheckoutLine($this->item()->id, packets: 10),
            new CheckoutLine($this->item()->id, packets: 10),
            new CheckoutLine($this->item()->id, packets: 10),
        ], discount: 10000);

        $this->assertEquals([3333, 3333, 3334], $order->items()->orderBy('id')->pluck('discount')->all());
        $this->assertEquals(35000, $order->total_amount);
    }

    /**
     * EN: The discount comes off the profit, line by line: the line of 15,000 that cost 1,000 with its share of 3,000 earns 11,000,
     *     the line of 7,500 that cost 500 with its share of 1,500 earns 5,500, and the order earns 16,500.
     * AR: الخصم ينطرح من الربح، بند بند: بند الـ 15,000 كلفته 1,000 وحصته 3,000 يربح 11,000،
     *     وبند الـ 7,500 كلفته 500 وحصته 1,500 يربح 5,500، والأوردر يربح 16,500.
     */
    public function test_the_discount_comes_off_the_profit_of_each_line(): void
    {
        $order = $this->sell($this->twoLines(), discount: 4500);

        $this->assertEquals([11000, 5500], $order->items()->orderBy('id')->pluck('profit')->all());
        $this->assertEquals(16500, $order->profit);
    }

    /**
     * EN: A postpaid customer owes the order after its discount: 22,500 with a discount of 4,500 leaves a debt of 18,000.
     * AR: الزبون الآجل يطلب الأوردر بعد خصمه: 22,500 بخصم 4,500 يصير الدين 18,000.
     */
    public function test_a_postpaid_customer_owes_the_order_after_its_discount(): void
    {
        $customer = Customer::factory()->create();

        $order = $this->sell($this->twoLines(), OrderPaymentType::Postpaid, $customer, discount: 4500);

        $this->assertEquals(18000, $order->remaining);
        $this->assertEquals(18000, $customer->debt());
    }

    /**
     * EN: The down payment is checked against the order after its discount: 22,500 with a discount of 4,500 is 18,000,
     *     so a down payment of 19,000 is refused and nothing is saved.
     * AR: الدفعة الأولية تنقاس على الأوردر بعد خصمه: 22,500 بخصم 4,500 يصير 18,000،
     *     فدفعة أولية 19,000 تنرفض وما ينحفظ شي.
     */
    public function test_a_down_payment_above_the_order_after_its_discount_is_refused(): void
    {
        $this->assertSaleIsRefused($this->twoLines(), discount: 4500, paymentType: OrderPaymentType::Postpaid, downPayment: 19000);
    }

    /**
     * EN: The discounts an order of 22,500 that cost 1,500 cannot take: a discount below zero, a discount of 21,500 that leaves 1,000
     *     (below the order's cost of 1,500), a discount of the whole 22,500, and a discount of 23,000, more than the order.
     * AR: الخصومات اللي ما يقبلها أوردر بـ 22,500 كلفته 1,500: خصم أقل من صفر، وخصم 21,500 يخلي 1,000
     *     (أقل من كلفة الأوردر 1,500)، وخصم بكد الأوردر كله 22,500، وخصم 23,000 أكثر من الأوردر.
     *
     * @return array<string, array{float}>
     */
    public static function discountsThatCannotBeTaken(): array
    {
        return [
            'below zero' => [-500],
            'below the cost of the order' => [21500],
            'the whole order' => [22500],
            'more than the order' => [23000],
        ];
    }

    /**
     * EN: A discount the order cannot take refuses the whole sale, and nothing is saved.
     * AR: الخصم اللي ما يقبله الأوردر يرفض البيعة كلها، وما ينحفظ شي.
     */
    #[DataProvider('discountsThatCannotBeTaken')]
    public function test_a_discount_the_order_cannot_take_is_refused(float $discount): void
    {
        $this->assertSaleIsRefused($this->twoLines(), discount: $discount);
    }

    /**
     * EN: The cost is checked on the whole order, not line by line: 10 packets of item A (15,000, cost 1,000) and 10 packets of
     *     item B sold at 110 a dozen (1,100, cost 1,000), with a discount of 1,610 (10%). B's share of 110 leaves B at 990, below its
     *     own cost, but the order is 14,490, far above its cost of 2,000, so the sale is accepted.
     * AR: الكلفة تنقاس على الأوردر كله، مو بند بند: 10 باكيتات من المادة A (15,000، كلفتها 1,000) و10 باكيتات من المادة B
     *     تنباع بـ 110 للدرزن (1,100، كلفتها 1,000)، بخصم 1,610 (10%). حصة B هي 110 فيصير B بـ 990، أقل من كلفته هو،
     *     بس الأوردر 14,490، أعلى بهواية من كلفته 2,000، فالبيعة تنقبل.
     */
    public function test_the_cost_is_checked_on_the_whole_order_and_not_on_each_line(): void
    {
        $order = $this->sell([
            new CheckoutLine($this->item()->id, packets: 10),
            new CheckoutLine($this->item(salePrice: 110)->id, packets: 10),
        ], discount: 1610);

        $this->assertEquals([1500, 110], $order->items()->orderBy('id')->pluck('discount')->all());
        $this->assertEquals(14490, $order->total_amount);
    }

    /**
     * EN: An edit spreads the discount it is given over the order's lines as they are after it: an order of 10 packets sold with a
     *     discount of 1,000 is edited to add 5 packets of another item and a discount of 3,000. The line of 15,000 takes 2,000,
     *     the new line of 7,500 takes 1,000, and the order becomes 19,500.
     * AR: التعديل يوزّع الخصم اللي ينطاله على بنود الأوردر مثل ما تصير بعده: أوردر بيه 10 باكيتات انباع بخصم 1,000
     *     يتعدل ويضاف له 5 باكيتات من مادة ثانية وخصم 3,000. بند الـ 15,000 ياخذ 2,000،
     *     والبند الجديد الـ 7,500 ياخذ 1,000، والأوردر يصير 19,500.
     */
    public function test_an_edit_spreads_the_new_discount_over_the_lines(): void
    {
        $item = $this->item();
        $order = $this->sell([new CheckoutLine($item->id, packets: 10)], discount: 1000);

        $order = (new UpdateOrder)->handle($order, $this->editData($order, [
            new CheckoutLine($item->id, packets: 10),
            new CheckoutLine($this->item()->id, packets: 5),
        ], discount: 3000))->fresh();

        $this->assertEquals([2000, 1000], $order->items()->orderBy('id')->pluck('discount')->all());
        $this->assertEquals(22500, $order->amount);
        $this->assertEquals(19500, $order->total_amount);
    }

    /**
     * EN: A discount never takes an order below what was paid on it: a postpaid order of 15,000 with a down payment of 14,000
     *     is edited to a discount of 2,000, which would make it 13,000. The edit is refused and the order stays as it was.
     * AR: الخصم ما ينزّل الأوردر تحت اللي انسدد منه: أوردر آجل بـ 15,000 عليه دفعة أولية 14,000
     *     يتعدل لخصم 2,000، يعني يصير 13,000. التعديل ينرفض والأوردر يبقى مثل ما چان.
     */
    public function test_an_edit_discount_never_takes_the_order_below_what_was_paid(): void
    {
        $item = $this->item();
        $order = $this->sell([new CheckoutLine($item->id, packets: 10)], OrderPaymentType::Postpaid, downPayment: 14000);

        try {
            (new UpdateOrder)->handle($order, $this->editData($order, [new CheckoutLine($item->id, packets: 10)], discount: 2000));

            $this->fail('The edit was not refused.');
        } catch (ValidationException) {
            $this->assertEquals(0, $order->items()->sole()->discount);
            $this->assertEquals(15000, $order->fresh()->total_amount);
        }
    }

    /**
     * EN: Sells the lines through the checkout: by default in cash, to a new customer, by a new seller, and returns the order fresh.
     * AR: يبيع البنود عن طريق الـ checkout: افتراضياً كاش، لزبون جديد، من بيّاع جديد، ويرجع الأوردر fresh.
     *
     * @param  list<CheckoutLine>  $lines
     */
    private function sell(
        array $lines,
        OrderPaymentType $paymentType = OrderPaymentType::Cash,
        ?Customer $customer = null,
        ?float $downPayment = null,
        float $discount = 0,
    ): Order {
        return (new Checkout)->handle(User::factory()->create(), new CheckoutData(
            customerId: ($customer ?? Customer::factory()->create())->id,
            paymentType: $paymentType,
            date: '2026-10-29',
            lines: $lines,
            downPayment: $downPayment,
            discount: $discount,
        ))->fresh();
    }

    /**
     * EN: Asserts that the sale throws a validation error and saves no order and no line.
     * AR: يتأكد إن البيعة ترمي خطأ تحقق وما تحفظ لا أوردر ولا بند.
     *
     * @param  list<CheckoutLine>  $lines
     */
    private function assertSaleIsRefused(
        array $lines,
        float $discount,
        OrderPaymentType $paymentType = OrderPaymentType::Cash,
        ?float $downPayment = null,
    ): void {
        try {
            $this->sell($lines, $paymentType, downPayment: $downPayment, discount: $discount);

            $this->fail('The sale was not refused.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
        }
    }

    /**
     * EN: The order's data as the edit screen sends it, with the given lines and discount.
     * AR: بيانات الأوردر مثل ما ترسلها شاشة التعديل، بالبنود والخصم المعطاة.
     *
     * @param  list<CheckoutLine>  $lines
     */
    private function editData(Order $order, array $lines, float $discount = 0): CheckoutData
    {
        return new CheckoutData(
            customerId: $order->customer_id,
            paymentType: $order->payment_type,
            date: $order->date->toDateString(),
            lines: $lines,
            discount: $discount,
        );
    }

    /**
     * EN: Two lines of two items: 10 packets (15,000, cost 1,000) and 5 packets (7,500, cost 500), 22,500 that cost 1,500.
     * AR: بندين لمادتين: 10 باكيتات (15,000، كلفتها 1,000) و5 باكيتات (7,500، كلفتها 500)، يعني 22,500 كلفتها 1,500.
     *
     * @return list<CheckoutLine>
     */
    private function twoLines(): array
    {
        return [
            new CheckoutLine($this->item()->id, packets: 10),
            new CheckoutLine($this->item()->id, packets: 5),
        ];
    }

    /**
     * EN: Creates an item in packets of 12 pieces (one dozen), 100 packets in stock, at the given price of a dozen that cost 100.
     * AR: ينشئ مادة باكيتها 12 قطعة (درزن واحد)، بالمخزون 100 باكيت، بسعر الدرزن المحدد وكلفة 100.
     */
    private function item(float $salePrice = 1500): Item
    {
        return Item::factory()->stocked(100)->create(['sale_price' => $salePrice, 'pieces_per_packet' => 12]);
    }
}
