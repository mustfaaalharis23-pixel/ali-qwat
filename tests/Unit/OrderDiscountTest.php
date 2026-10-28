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
 * Every item in the examples is sold in packets of 12 pieces (one dozen) at 1,500 a dozen, and a dozen cost us 100,
 * so 10 packets are 10 dozens: 15,000, and they cost 1,000.
 */
class OrderDiscountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: 10 packets (15,000) are sold in cash with a discount of 2,000 on the line. The line keeps its total of 15,000 and records
     *     the discount of 2,000. The order is 15,000 before the discount and 13,000 after it, the cash order is paid 13,000,
     *     and nothing remains on it.
     * AR: انباع 10 باكيتات (15,000) كاش بخصم 2,000 على البند. البند يبقى مجموعه 15,000 وينسجل عليه خصم 2,000.
     *     الأوردر 15,000 قبل الخصم و13,000 بعده، والأوردر الكاش مدفوع 13,000، وما باقي عليه شي.
     */
    public function test_the_line_discount_comes_off_the_order_total(): void
    {
        $order = $this->sell([new CheckoutLine($this->item()->id, packets: 10, discount: 2000)]);

        $line = $order->items()->sole();
        $this->assertEquals(15000, $line->total_price);
        $this->assertEquals(2000, $line->discount);
        $this->assertEquals(15000, $order->amount);
        $this->assertEquals(13000, $order->total_amount);
        $this->assertEquals(13000, $order->paid_amount);
        $this->assertEquals(0, $order->remaining);
    }

    /**
     * EN: The discount comes off the profit: 10 packets of 15,000 that cost 1,000, with a discount of 2,000, leave a profit
     *     of 15,000 − 2,000 − 1,000 = 12,000, on the line and on the order.
     * AR: الخصم ينطرح من الربح: 10 باكيتات بـ 15,000 كلفتها 1,000، بخصم 2,000، ربحها 15,000 − 2,000 − 1,000 = 12,000،
     *     على البند وعلى الأوردر.
     */
    public function test_the_discount_comes_off_the_profit(): void
    {
        $order = $this->sell([new CheckoutLine($this->item()->id, packets: 10, discount: 2000)]);

        $this->assertEquals(12000, $order->items()->sole()->profit);
        $this->assertEquals(12000, $order->profit);
    }

    /**
     * EN: Each line has its own discount: two lines of 15,000, one with a discount of 2,000 and one without, make an order
     *     of 30,000 before the discount and 28,000 after it.
     * AR: كل بند إله خصمه: بندين كل واحد 15,000، واحد بخصم 2,000 والثاني بدون، يطلع الأوردر 30,000 قبل الخصم و28,000 بعده.
     */
    public function test_each_line_has_its_own_discount(): void
    {
        $order = $this->sell([
            new CheckoutLine($this->item()->id, packets: 10, discount: 2000),
            new CheckoutLine($this->item()->id, packets: 10),
        ]);

        $this->assertEquals([2000, 0], $order->items()->orderBy('id')->pluck('discount')->all());
        $this->assertEquals(30000, $order->amount);
        $this->assertEquals(28000, $order->total_amount);
    }

    /**
     * EN: A postpaid customer owes the order after its discount: 15,000 with a discount of 2,000 leaves a debt of 13,000.
     * AR: الزبون الآجل يطلب الأوردر بعد خصمه: 15,000 بخصم 2,000 يصير الدين 13,000.
     */
    public function test_a_postpaid_customer_owes_the_order_after_its_discount(): void
    {
        $customer = Customer::factory()->create();

        $order = $this->sell([new CheckoutLine($this->item()->id, packets: 10, discount: 2000)], OrderPaymentType::Postpaid, $customer);

        $this->assertEquals(13000, $order->remaining);
        $this->assertEquals(13000, $customer->debt());
    }

    /**
     * EN: The down payment is checked against the order after its discount: on 15,000 with a discount of 2,000 the order is 13,000,
     *     so a down payment of 14,000 is refused and nothing is saved.
     * AR: الدفعة الأولية تنقاس على الأوردر بعد خصمه: 15,000 بخصم 2,000 يصير الأوردر 13,000،
     *     فدفعة أولية 14,000 تنرفض وما ينحفظ شي.
     */
    public function test_a_down_payment_above_the_order_after_its_discount_is_refused(): void
    {
        $this->assertSaleIsRefused(
            [new CheckoutLine($this->item()->id, packets: 10, discount: 2000)],
            OrderPaymentType::Postpaid,
            downPayment: 14000,
        );
    }

    /**
     * EN: The discounts a line cannot take, on 10 packets of 15,000 that cost 1,000: a discount below zero, a discount of 14,500
     *     that leaves 500 (below the cost of 1,000), and a discount of 16,000, more than the line itself.
     * AR: الخصومات اللي ما يقبلها البند، على 10 باكيتات بـ 15,000 كلفتها 1,000: خصم أقل من صفر، وخصم 14,500
     *     يخلي 500 (أقل من الكلفة 1,000)، وخصم 16,000 أكثر من البند نفسه.
     *
     * @return array<string, array{float}>
     */
    public static function discountsThatCannotBeTaken(): array
    {
        return [
            'below zero' => [-500],
            'below the cost' => [14500],
            'more than the line' => [16000],
        ];
    }

    /**
     * EN: A discount the line cannot take refuses the whole sale, and nothing is saved.
     * AR: الخصم اللي ما يقبله البند يرفض البيعة كلها، وما ينحفظ شي.
     */
    #[DataProvider('discountsThatCannotBeTaken')]
    public function test_a_discount_the_line_cannot_take_is_refused(float $discount): void
    {
        $this->assertSaleIsRefused([new CheckoutLine($this->item()->id, packets: 10, discount: $discount)]);
    }

    /**
     * EN: An edit saves the discount it is given: a line of 15,000 sold with a discount of 1,000 is edited to a discount of 3,000.
     *     It stays the same line, records 3,000, and the order becomes 12,000.
     * AR: التعديل يحفظ الخصم اللي ينطاله: بند بـ 15,000 انباع بخصم 1,000 يتعدل لخصم 3,000.
     *     يبقى نفس البند، وينسجل عليه 3,000، والأوردر يصير 12,000.
     */
    public function test_an_edit_saves_the_new_discount(): void
    {
        $item = $this->item();
        $order = $this->sell([new CheckoutLine($item->id, packets: 10, discount: 1000)]);
        $soldLine = $order->items()->sole();

        $order = (new UpdateOrder)->handle($order, $this->editData($order, [new CheckoutLine($item->id, packets: 10, discount: 3000)]))->fresh();

        $line = $order->items()->sole();
        $this->assertSame($soldLine->id, $line->id);
        $this->assertEquals(3000, $line->discount);
        $this->assertEquals(15000, $order->amount);
        $this->assertEquals(12000, $order->total_amount);
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
            (new UpdateOrder)->handle($order, $this->editData($order, [new CheckoutLine($item->id, packets: 10, discount: 2000)]));

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
    ): Order {
        return (new Checkout)->handle(User::factory()->create(), new CheckoutData(
            customerId: ($customer ?? Customer::factory()->create())->id,
            paymentType: $paymentType,
            date: '2026-10-28',
            lines: $lines,
            downPayment: $downPayment,
        ))->fresh();
    }

    /**
     * EN: Asserts that the sale throws a validation error and saves no order and no line.
     * AR: يتأكد إن البيعة ترمي خطأ تحقق وما تحفظ لا أوردر ولا بند.
     *
     * @param  list<CheckoutLine>  $lines
     */
    private function assertSaleIsRefused(array $lines, OrderPaymentType $paymentType = OrderPaymentType::Cash, ?float $downPayment = null): void
    {
        try {
            $this->sell($lines, $paymentType, downPayment: $downPayment);

            $this->fail('The sale was not refused.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
        }
    }

    /**
     * EN: The order's data as the edit screen sends it, with the given lines.
     * AR: بيانات الأوردر مثل ما ترسلها شاشة التعديل، بالبنود المعطاة.
     *
     * @param  list<CheckoutLine>  $lines
     */
    private function editData(Order $order, array $lines): CheckoutData
    {
        return new CheckoutData(
            customerId: $order->customer_id,
            paymentType: $order->payment_type,
            date: $order->date->toDateString(),
            lines: $lines,
        );
    }

    /**
     * EN: Creates an item in packets of 12 pieces (one dozen), 100 packets in stock, at 1,500 a dozen that cost 100.
     * AR: ينشئ مادة باكيتها 12 قطعة (درزن واحد)، بالمخزون 100 باكيت، بسعر 1,500 للدرزن وكلفة 100.
     */
    private function item(): Item
    {
        return Item::factory()->stocked(100)->create(['sale_price' => 1500, 'pieces_per_packet' => 12]);
    }
}
