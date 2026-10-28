<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use App\Orders\PayCustomerDebt;
use App\Orders\UpdateOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given an item in packets of 12 pieces at power 1.50, each packet 6 red and 6 blue, 2 packets of it are sold at 15,000 a dozen,
     *     and later the item's price is raised to 18,000. The order is edited to 3 packets of the same power and colors.
     *     It stays the same line at the price it was sold at: 3 dozens × 15,000 = 45,000, and one more packet leaves the stock.
     * AR: مادة باكيتها 12 قطعة بدرجة 1.50، وكل باكيت بيه 6 أحمر و6 أزرق، انباع منها 2 باكيت بـ 15,000 للدرزن،
     *     وبعدين سعر المادة صار 18,000. الأوردر يتعدل لـ 3 باكيتات بنفس الدرجة والألوان.
     *     يبقى نفس البند وبسعر بيعه القديم: 3 درازن × 15,000 = 45,000، وباكيت زيادة يطلع من المخزون.
     */
    public function test_a_kept_line_keeps_the_price_it_was_sold_at(): void
    {
        [$item, $power, $colors] = $this->itemAtPowerWithColors(salePrice: 15000, packets: 10);
        $order = $this->sell(lines: [new CheckoutLine($item->id, lensPowerId: $power->id, packets: 2, colors: $colors)]);
        $soldLine = $order->items()->sole();
        $item->update(['sale_price' => 18000]);

        $order = $this->edit($order, lines: [new CheckoutLine($item->id, lensPowerId: $power->id, packets: 3, colors: $colors)]);

        $line = $order->items()->sole();
        $this->assertSame($soldLine->id, $line->id);
        $this->assertEquals(36, $line->pieces);
        $this->assertEquals(15000, $line->price);
        $this->assertEquals(45000, $line->total_price);
        $this->assertEquals(45000, $order->total_amount);
        $this->assertEquals(7, $item->fresh()->available_quantity);
    }

    /**
     * EN: Given an order of 2 packets of item A, the edit takes A out and adds 1 packet of item B, whose price now is 9,000 a dozen.
     *     A's line is deleted and its 2 dozens go back to A's stock; B's line takes B's price of now, and one dozen of B leaves the stock.
     *     The order is 9,000.
     * AR: أوردر بيه 2 باكيت من المادة A، والتعديل يشيل A ويضيف باكيت واحد من المادة B، وسعرها هسه 9,000 للدرزن.
     *     بند A ينحذف ودرازنه الـ 2 ترجع لمخزون A، وبند B ياخذ سعر B الحالي، ودرزن من B يطلع من المخزون.
     *     الأوردر يصير 9,000.
     */
    public function test_a_new_line_takes_the_price_of_now_and_a_removed_line_returns_its_stock(): void
    {
        $itemA = $this->packetItem(salePrice: 15000, packets: 100);
        $itemB = $this->packetItem(salePrice: 9000, packets: 100);
        $order = $this->sell(lines: [new CheckoutLine($itemA->id, packets: 2)]);

        $order = $this->edit($order, lines: [new CheckoutLine($itemB->id, packets: 1)]);

        $line = $order->items()->sole();
        $this->assertSame($itemB->id, $line->item_id);
        $this->assertEquals(9000, $line->price);
        $this->assertEquals(9000, $order->total_amount);
        $this->assertEquals(100, $itemA->fresh()->available_quantity);
        $this->assertEquals(99, $itemB->fresh()->available_quantity);
    }

    /**
     * EN: Given an item with 5 packets in stock and an order that took 2 of them, 3 are left. The order can be edited to 5 packets,
     *     its own 2 and the 3 left. Editing it to 6 is refused and nothing changes.
     * AR: مادة بمخزونها 5 باكيتات، وأوردر أخذ منها 2، فبقى 3. الأوردر يكدر يتعدل لـ 5 باكيتات،
     *     الـ 2 مالته والـ 3 الباقية. تعديله لـ 6 ينرفض وما يتغير شي.
     */
    public function test_a_kept_line_grows_by_what_is_left_and_no_more(): void
    {
        $item = $this->packetItem(packets: 5);
        $order = $this->sell(lines: [new CheckoutLine($item->id, packets: 2)]);

        $order = $this->edit($order, lines: [new CheckoutLine($item->id, packets: 5)]);
        $this->assertEquals(60, $order->items()->sole()->pieces);
        $this->assertEquals(0, $item->fresh()->available_quantity);

        $this->assertEditIsRejected($order, $this->data($order, lines: [new CheckoutLine($item->id, packets: 6)]));
    }

    /**
     * EN: Given Ahmed's cash order of 2,000, the edit moves it to Sara, postpaid, on 2026-10-01, with notes and a weekly payment of 500.
     *     All of them are saved, the user who made the sale stays the same, and the order becomes Sara's debt in full: Sara owes 2,000 and Ahmed nothing.
     * AR: أوردر كاش لأحمد بـ 2,000، والتعديل ينقله لسارة، آجل، بتاريخ 2026-10-01، ويا ملاحظات ودفعة أسبوعية 500.
     *     كلهم ينحفظون، واليوزر اللي سوى البيعة يبقى نفسه، والأوردر يصير دين كامل على سارة: سارة مطلوبة 2,000 وأحمد ولا شي.
     */
    public function test_the_order_details_are_saved(): void
    {
        $ahmed = Customer::factory()->create();
        $sara = Customer::factory()->create();
        $seller = User::factory()->create();
        $item = $this->packetItem(salePrice: 1000);
        $order = $this->sell(customer: $ahmed, lines: [new CheckoutLine($item->id, packets: 2)], seller: $seller);

        $order = $this->edit($order, customer: $sara, paymentType: OrderPaymentType::Postpaid, date: '2026-10-01', notes: 'Delivered to the shop', weeklyPayment: 500);

        $this->assertSame($sara->id, $order->customer_id);
        $this->assertSame(OrderPaymentType::Postpaid, $order->payment_type);
        $this->assertSame('2026-10-01', $order->date->toDateString());
        $this->assertSame('Delivered to the shop', $order->notes);
        $this->assertEquals(500, $order->weekly_payment);
        $this->assertSame($seller->id, $order->user_id);
        $this->assertEquals(2000, $order->remaining);
        $this->assertEquals(2000, $sara->debt());
        $this->assertEquals(0, $ahmed->debt());
    }

    /**
     * EN: Given a postpaid order of 10 packets × 1,000 = 10,000 with 6,000 paid on it, editing it to 5 packets (5,000) is refused and nothing changes.
     *     6 packets (6,000) is accepted and nothing is left on it; 12 packets (12,000) is accepted and 6,000 is left.
     * AR: أوردر آجل بـ 10 باكيتات × 1,000 = 10,000 ومدفوع عليه 6,000، تعديله لـ 5 باكيتات (5,000) ينرفض وما يتغير شي.
     *     6 باكيتات (6,000) تنقبل وما يبقى عليه شي، و12 باكيت (12,000) تنقبل ويبقى عليه 6,000.
     */
    public function test_an_order_with_payments_never_drops_below_what_was_paid(): void
    {
        $customer = Customer::factory()->create();
        $item = $this->packetItem(salePrice: 1000);
        $order = $this->sell(customer: $customer, paymentType: OrderPaymentType::Postpaid, lines: [new CheckoutLine($item->id, packets: 10)]);
        $this->pay($customer, 6000);

        $this->assertEditIsRejected($order, $this->data($order, lines: [new CheckoutLine($item->id, packets: 5)]));

        $order = $this->edit($order, lines: [new CheckoutLine($item->id, packets: 6)]);
        $this->assertEquals(6000, $order->paid_amount);
        $this->assertEquals(0, $order->remaining);

        $order = $this->edit($order, lines: [new CheckoutLine($item->id, packets: 12)]);
        $this->assertEquals(6000, $order->paid_amount);
        $this->assertEquals(6000, $order->remaining);
    }

    /**
     * EN: The changes that a postpaid order with a down payment of 1,000 on it cannot take: another customer, or cash.
     * AR: التغييرات اللي ما يقبلها أوردر آجل عليه عربون 1,000: زبون ثاني، أو كاش.
     *
     * @return array<string, array{string}>
     */
    public static function changesOfAPaidOrder(): array
    {
        return [
            'another customer' => ['customer'],
            'cash' => ['payment_type'],
        ];
    }

    /**
     * EN: An order with a payment on it keeps its customer and its payment type: moving it to another customer, or making it cash,
     *     is refused and nothing changes.
     * AR: الأوردر اللي عليه تسديد يبقى بنفس زبونه ونفس طريقة دفعه: نقله لزبون ثاني، أو تحويله كاش،
     *     ينرفض وما يتغير شي.
     */
    #[DataProvider('changesOfAPaidOrder')]
    public function test_an_order_with_payments_keeps_its_customer_and_payment_type(string $change): void
    {
        $item = $this->packetItem(salePrice: 1000);
        $order = $this->sell(paymentType: OrderPaymentType::Postpaid, lines: [new CheckoutLine($item->id, packets: 5)], downPayment: 1000);

        $this->assertEditIsRejected($order, $change === 'customer'
            ? $this->data($order, customer: Customer::factory()->create())
            : $this->data($order, paymentType: OrderPaymentType::Cash));
    }

    /**
     * EN: There is no order without lines: an edit that takes every line out is refused and nothing changes.
     * AR: ماكو أوردر بدون بنود: التعديل اللي يشيل كل البنود ينرفض وما يتغير شي.
     */
    public function test_an_order_is_not_left_without_lines(): void
    {
        $order = $this->sell(lines: [new CheckoutLine($this->packetItem()->id, packets: 2)]);

        $this->assertEditIsRejected($order, $this->data($order, lines: []));
    }

    /**
     * EN: The weekly payments an edit cannot take: a weekly payment on a cash order, and a weekly payment of 7,000
     *     on a postpaid order of 10,000 with a down payment of 4,000, where only 6,000 is left.
     * AR: الدفعات الأسبوعية اللي ما يقبلها التعديل: دفعة أسبوعية على أوردر كاش، ودفعة أسبوعية 7,000
     *     على أوردر آجل بـ 10,000 عليه عربون 4,000، يعني الباقي بس 6,000.
     *
     * @return array<string, array{OrderPaymentType, ?float, float}>
     */
    public static function weeklyPaymentsThatCannotBeTaken(): array
    {
        return [
            'on a cash order' => [OrderPaymentType::Cash, null, 500],
            'more than what is left after the down payment' => [OrderPaymentType::Postpaid, 4000, 7000],
        ];
    }

    /**
     * EN: The weekly payment follows the same rules as at the sale: postpaid only, and not more than what is left after the down payment.
     *     An edit that breaks them is refused and nothing changes.
     * AR: الدفعة الأسبوعية تمشي على نفس قواعد البيع: للآجل بس، ومو أكثر من الباقي بعد العربون.
     *     التعديل اللي يكسرها ينرفض وما يتغير شي.
     */
    #[DataProvider('weeklyPaymentsThatCannotBeTaken')]
    public function test_a_weekly_payment_that_cannot_be_taken_is_refused(OrderPaymentType $paymentType, ?float $downPayment, float $weeklyPayment): void
    {
        $order = $this->sell(
            paymentType: OrderPaymentType::Postpaid,
            lines: [new CheckoutLine($this->packetItem(salePrice: 1000)->id, packets: 10)],
            downPayment: $downPayment,
        );

        $this->assertEditIsRejected($order, $this->data($order, paymentType: $paymentType, weeklyPayment: $weeklyPayment));
    }

    /**
     * EN: Given Ali, an invoice-by-invoice customer, with an unpaid postpaid order. Moving Ahmed's order to Ali is refused, because Ali owes.
     *     Editing Ali's own unpaid order is accepted: the debt it carries is the order itself.
     * AR: علي زبون فاتورة بفاتورة، وعليه أوردر آجل ما مسدد. نقل أوردر أحمد لعلي ينرفض، لأن علي مطلوب.
     *     تعديل أوردر علي نفسه اللي ما مسدد ينقبل: الدين اللي عليه هو نفس الأوردر.
     */
    public function test_an_invoice_by_invoice_customer_who_owes_does_not_take_another_order_by_an_edit(): void
    {
        $ali = Customer::factory()->create(['is_invoice_by_invoice' => true]);
        $item = $this->packetItem(salePrice: 1000);
        $alisOrder = $this->sell(customer: $ali, paymentType: OrderPaymentType::Postpaid, lines: [new CheckoutLine($item->id, packets: 2)]);
        $ahmedsOrder = $this->sell(customer: Customer::factory()->create(), lines: [new CheckoutLine($item->id, packets: 1)]);

        $this->assertEditIsRejected($ahmedsOrder, $this->data($ahmedsOrder, customer: $ali));

        $alisOrder = $this->edit($alisOrder, lines: [new CheckoutLine($item->id, packets: 3)]);
        $this->assertEquals(3000, $ali->debt());
    }

    /**
     * EN: Edits the order through the action, keeping whatever is not given as it is on the order, and returns it fresh.
     * AR: يعدل الأوردر عن طريق الـ action، واللي ما ينطى يبقى مثل ما هو على الأوردر، ويرجعه fresh.
     *
     * @param  list<CheckoutLine>|null  $lines
     */
    private function edit(
        Order $order,
        ?Customer $customer = null,
        ?OrderPaymentType $paymentType = null,
        ?string $date = null,
        ?array $lines = null,
        ?string $notes = null,
        ?float $weeklyPayment = null,
    ): Order {
        return (new UpdateOrder)->handle($order, $this->data($order, $customer, $paymentType, $date, $lines, $notes, $weeklyPayment))->fresh();
    }

    /**
     * EN: The order's data as the edit screen sends it: what is given, and the order's own values for the rest.
     * AR: بيانات الأوردر مثل ما ترسلها شاشة التعديل: اللي ينطى، وقيم الأوردر نفسها للباقي.
     *
     * @param  list<CheckoutLine>|null  $lines
     */
    private function data(
        Order $order,
        ?Customer $customer = null,
        ?OrderPaymentType $paymentType = null,
        ?string $date = null,
        ?array $lines = null,
        ?string $notes = null,
        ?float $weeklyPayment = null,
    ): CheckoutData {
        $order = $order->fresh();

        return new CheckoutData(
            customerId: $customer->id ?? $order->customer_id,
            paymentType: $paymentType ?? $order->payment_type,
            date: $date ?? $order->date->toDateString(),
            lines: $lines ?? $order->items->map(fn (OrderItem $line): CheckoutLine => new CheckoutLine(
                $line->item_id,
                lensPowerId: $line->lens_power_id,
                packets: (int) $line->packets(),
                colors: $line->colors ?? [],
            ))->all(),
            notes: $notes ?? $order->notes,
            weeklyPayment: $weeklyPayment ?? $order->weekly_payment,
        );
    }

    /**
     * EN: Asserts that the edit throws a validation error and leaves the order, its lines, and the stock as they were.
     * AR: يتأكد إن التعديل يرمي خطأ تحقق ويخلي الأوردر وبنوده والمخزون مثل ما جانوا.
     */
    private function assertEditIsRejected(Order $order, CheckoutData $data): void
    {
        $before = $this->snapshot($order);

        try {
            (new UpdateOrder)->handle($order->fresh(), $data);

            $this->fail('The edit was not rejected.');
        } catch (ValidationException) {
            $this->assertEquals($before, $this->snapshot($order));
        }
    }

    /**
     * EN: The order, its lines (deleted ones too), and every item's stock, as they are in the database now.
     * AR: الأوردر وبنوده (حتى المحذوفة) ومخزون كل المواد، مثل ما هم بالداتابيس هسه.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        return [
            'order' => Order::withTrashed()->findOrFail($order->id)->getAttributes(),
            'lines' => OrderItem::withTrashed()->where('order_id', $order->id)->orderBy('id')->get()->map->getAttributes()->all(),
            'stock' => Item::query()->orderBy('id')->pluck('available_quantity', 'id')->all(),
        ];
    }

    /**
     * EN: Sells the lines through the checkout: by default to a new customer, in cash, by a new seller.
     * AR: يبيع البنود عن طريق الـ checkout: افتراضياً لزبون جديد، كاش، من بيّاع جديد.
     *
     * @param  list<CheckoutLine>  $lines
     */
    private function sell(
        array $lines,
        ?Customer $customer = null,
        OrderPaymentType $paymentType = OrderPaymentType::Cash,
        ?float $downPayment = null,
        ?User $seller = null,
    ): Order {
        return (new Checkout)->handle($seller ?? User::factory()->create(), new CheckoutData(
            customerId: ($customer ?? Customer::factory()->create())->id,
            paymentType: $paymentType,
            date: '2026-09-20',
            lines: $lines,
            downPayment: $downPayment,
        ))->fresh();
    }

    /**
     * EN: Records a payment of the amount for the customer, made by a super user.
     * AR: يسجل تسديد بالمبلغ للزبون، يسويه يوزر سوبر.
     */
    private function pay(Customer $customer, float $amount): void
    {
        (new PayCustomerDebt)->handle(User::factory()->create(['is_super' => true]), $customer, $amount, '2026-09-25');
    }

    /**
     * EN: Creates an item in packets of 12 pieces (one dozen) with the given packets in stock, at the given price of a dozen and a cost of 100.
     * AR: ينشئ مادة باكيتها 12 قطعة (درزن واحد) بمخزون بعدد الباكيتات المحدد، بسعر الدرزن المحدد وكلفة 100.
     */
    private function packetItem(float $salePrice = 1000, int $packets = 100): Item
    {
        return Item::factory()->stocked($packets)->create(['sale_price' => $salePrice, 'pieces_per_packet' => 12]);
    }

    /**
     * EN: Creates an item in packets of 12 pieces bought at power 1.50, each packet holding 6 red pieces and 6 blue.
     * AR: ينشئ مادة باكيتها 12 قطعة انشترت بدرجة 1.50، وكل باكيت بيه 6 قطع أحمر و6 أزرق.
     *
     * @return array{Item, LensPower, list<array{color_id: int, pieces: int}>}
     */
    private function itemAtPowerWithColors(float $salePrice, int $packets): array
    {
        $item = Item::factory()->create(['sale_price' => $salePrice, 'pieces_per_packet' => 12]);
        $power = LensPower::factory()->create(['value' => 1.5]);
        $colors = [
            ['color_id' => Color::factory()->create(['name' => 'Red'])->id, 'pieces' => 6],
            ['color_id' => Color::factory()->create(['name' => 'Blue'])->id, 'pieces' => 6],
        ];
        $line = InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => $packets,
            'pieces_per_packet' => 12,
            'price' => 100,
        ]);
        $line->colors()->createMany($colors);
        $line->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => $packets]);

        return [$item->fresh(), $power, $colors];
    }
}
