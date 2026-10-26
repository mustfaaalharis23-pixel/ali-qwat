<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The checkout saves the order with its items, and the order's amounts are the sum of the items.
     * AR: الـ checkout يحفظ الأوردر وياه بنوده، ومبالغ الأوردر = مجموع البنود.
     */
    public function test_checkout_saves_the_order_with_its_items(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();

        $order = (new Checkout)->handle($user, new CheckoutData(
            customerId: $customer->id,
            paymentType: OrderPaymentType::Cash,
            date: '2026-09-15',
            lines: [
                new CheckoutLine($this->pricedItem(salePrice: 1500)->id, quantity: 2),
                new CheckoutLine($this->pricedItem(salePrice: 750)->id, quantity: 1),
            ],
        ))->fresh();

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(OrderPaymentType::Cash, $order->payment_type);
        $this->assertSame(2, $order->items()->count());
        $this->assertEquals(3750, $order->amount);
        $this->assertEquals(3750, $order->total_amount);
    }

    /**
     * EN: The price of each sold item is taken from the item's sale price in the database, never from outside.
     * AR: سعر كل بند ينأخذ من سعر بيع المادة بالداتابيس، مو من برّا أبداً.
     */
    public function test_item_price_is_the_sale_price_from_the_database(): void
    {
        $item = $this->pricedItem(salePrice: 1250);

        $order = (new Checkout)->handle(User::factory()->create(), $this->checkoutData(lines: [
            new CheckoutLine($item->id, quantity: 2),
        ]));

        $line = $order->items()->sole();

        $this->assertEquals(1250, $line->price);
        $this->assertEquals(2500, $line->total_price);
    }

    /**
     * EN: If saving one item fails, neither the order nor any of its items is saved.
     * AR: إذا فشل حفظ بند واحد، لا الأوردر ينحفظ ولا أي بند من بنوده.
     */
    public function test_nothing_is_saved_when_one_item_fails(): void
    {
        $item = $this->pricedItem();

        $this->assertCheckoutIsRejected($this->checkoutData(lines: [
            new CheckoutLine($item->id, quantity: 2),
            new CheckoutLine($item->id, quantity: 0),
        ]));
    }

    /**
     * EN: There is no order without a customer: a checkout without a customer is rejected and nothing is saved.
     * AR: ماكو أوردر بدون زبون: الـ checkout بدون زبون ينرفض وما ينحفظ شي.
     */
    public function test_order_without_a_customer_is_rejected(): void
    {
        $this->assertCheckoutIsRejected($this->checkoutData(customerId: null));
    }

    /**
     * EN: A checkout for a customer that does not exist is rejected and nothing is saved.
     * AR: الـ checkout لزبون مو موجود ينرفض وما ينحفظ شي.
     */
    public function test_order_for_a_customer_that_does_not_exist_is_rejected(): void
    {
        $this->assertCheckoutIsRejected($this->checkoutData(customerId: 999999));
    }

    /**
     * EN: A checkout for a deleted customer is rejected and nothing is saved.
     * AR: الـ checkout لزبون محذوف ينرفض وما ينحفظ شي.
     */
    public function test_order_for_a_deleted_customer_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $this->assertCheckoutIsRejected($this->checkoutData(customerId: $customer->id));
    }

    /**
     * EN: An order without a payment type does not enter the system: the checkout is rejected and nothing is saved.
     * AR: الأوردر بدون طريقة دفع ما يدخل للنظام: الـ checkout ينرفض وما ينحفظ شي.
     */
    public function test_order_without_a_payment_type_is_rejected(): void
    {
        $this->assertCheckoutIsRejected($this->checkoutData(paymentType: null));
    }

    /**
     * EN: If an item has no sale price (zero), the whole order is rejected and nothing is saved.
     * AR: إذا مادة ما إلها سعر بيع (صفر)، الأوردر كله ينرفض وما ينحفظ شي.
     */
    public function test_order_with_an_item_without_a_sale_price_is_rejected(): void
    {
        $itemWithoutSalePrice = Item::factory()->create(['sale_price' => 0, 'cost_price' => 0]);

        $this->assertCheckoutIsRejected($this->checkoutData(lines: [
            new CheckoutLine($this->pricedItem()->id, quantity: 1),
            new CheckoutLine($itemWithoutSalePrice->id, quantity: 1),
        ]));
    }

    /**
     * EN: If an item's sale price is less than its cost price, the whole order is rejected and nothing is saved.
     * AR: إذا سعر بيع المادة أقل من سعر كلفتها، الأوردر كله ينرفض وما ينحفظ شي.
     */
    public function test_order_with_an_item_sold_below_its_cost_price_is_rejected(): void
    {
        $itemBelowCost = Item::factory()->create(['sale_price' => 900, 'cost_price' => 1000]);

        $this->assertCheckoutIsRejected($this->checkoutData(lines: [
            new CheckoutLine($this->pricedItem()->id, quantity: 1),
            new CheckoutLine($itemBelowCost->id, quantity: 1),
        ]));
    }

    /**
     * EN: An item sold at exactly its cost price is accepted, because only a sale price less than the cost is rejected.
     * AR: المادة الي سعر بيعها يساوي كلفتها بالضبط تنقبل، لأن بس السعر الأقل من الكلفة ينرفض.
     */
    public function test_item_sold_at_its_cost_price_is_accepted(): void
    {
        $item = Item::factory()->stocked(10, 1000)->create(['sale_price' => 1000]);

        $order = (new Checkout)->handle(User::factory()->create(), $this->checkoutData(lines: [
            new CheckoutLine($item->id, quantity: 1),
        ]));

        $this->assertEquals(1000, $order->fresh()->total_amount);
    }

    /**
     * EN: A checkout for more than the available quantity is rejected and nothing is saved.
     * AR: الچيك اوت بكمية أكثر من المتوفر ينرفض وما ينحفظ شي.
     */
    public function test_a_checkout_for_more_than_the_available_quantity_is_rejected(): void
    {
        $item = Item::factory()->stocked(5)->create(['sale_price' => 1000]);

        $this->assertCheckoutIsRejected($this->checkoutData(lines: [new CheckoutLine($item->id, quantity: 6)]));
    }

    /**
     * EN: Given an item in packets of 20 pieces bought at power 1.50, 2 packets of it are sold at that power.
     *     The sold line keeps power 1.50, and it is 40 pieces.
     * AR: مادة باكيتها 20 قطعة انشترت بدرجة 1.50، وانباع منها 2 باكيت بنفس الدرجة.
     *     البند المبيع يحفظ الدرجة 1.50، وبيه 40 قطعة.
     */
    public function test_checkout_saves_the_power_of_each_line(): void
    {
        $item = Item::factory()->create(['sale_price' => 1000, 'pieces_per_packet' => 20]);
        $power = LensPower::factory()->create(['value' => 1.5]);
        InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 10,
            'pieces_per_packet' => 20,
            'price' => 1000,
        ])->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => 10]);

        $order = (new Checkout)->handle(User::factory()->create(), $this->checkoutData(lines: [
            new CheckoutLine($item->id, packets: 2, lensPowerId: $power->id),
        ]));

        $this->assertSame($power->id, $order->items()->sole()->lens_power_id);
        $this->assertEquals(40, $order->items()->sole()->pieces);
    }

    /**
     * EN: Given an item in packets of 20 pieces bought at power 1.50, each packet holding 10 red pieces and 10 blue,
     *     2 packets of it are sold at that power in those colors. The sold line keeps the colors of its packets.
     * AR: مادة باكيتها 20 قطعة انشترت بدرجة 1.50، وكل باكيت بيه 10 قطع أحمر و10 أزرق،
     *     وانباع منها 2 باكيت بنفس الدرجة وبهاي الألوان. البند المبيع يحفظ ألوان باكيتاته.
     */
    public function test_checkout_saves_the_colors_of_each_line(): void
    {
        $item = Item::factory()->create(['sale_price' => 1000, 'pieces_per_packet' => 20]);
        $power = LensPower::factory()->create(['value' => 1.5]);
        $colors = [
            ['color_id' => Color::factory()->create(['name' => 'Red'])->id, 'pieces' => 10],
            ['color_id' => Color::factory()->create(['name' => 'Blue'])->id, 'pieces' => 10],
        ];
        $line = InvoiceItem::factory()->create([
            'invoice_id' => Invoice::factory()->purchase()->confirmed()->create()->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 5,
            'pieces_per_packet' => 20,
            'price' => 1000,
        ]);
        $line->colors()->createMany($colors);
        $line->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => 5]);

        $order = (new Checkout)->handle(User::factory()->create(), $this->checkoutData(lines: [
            new CheckoutLine($item->id, packets: 2, lensPowerId: $power->id, colors: $colors),
        ]));

        $this->assertEquals($colors, $order->items()->sole()->colors);
    }

    /**
     * EN: Asserts that the checkout throws a validation error and saves no order and no item.
     * AR: يتأكد إن الـ checkout يرمي خطأ تحقق وما يحفظ لا أوردر ولا بند.
     */
    private function assertCheckoutIsRejected(CheckoutData $data): void
    {
        try {
            (new Checkout)->handle(User::factory()->create(), $data);

            $this->fail('The checkout was not rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
        }
    }

    /**
     * EN: Builds valid checkout data for a new customer, paid in cash, with one priced item, unless other values are given.
     * AR: يبني بيانات checkout صحيحة لزبون جديد، دفع كاش، ببند واحد لمادة مسعّرة، إلا إذا انطت قيم ثانية.
     *
     * @param  list<CheckoutLine>|null  $lines
     */
    private function checkoutData(
        ?int $customerId = -1,
        ?OrderPaymentType $paymentType = OrderPaymentType::Cash,
        ?array $lines = null,
    ): CheckoutData {
        return new CheckoutData(
            customerId: $customerId === -1 ? Customer::factory()->create()->id : $customerId,
            paymentType: $paymentType,
            date: '2026-09-15',
            lines: $lines ?? [new CheckoutLine($this->pricedItem()->id, quantity: 1)],
        );
    }

    /**
     * EN: Creates an item that has stock, with the given sale price, and a cost price of 100 from its purchase.
     * AR: ينشئ مادة عندها مخزون، بسعر البيع المحدد، وكلفتها 100 من شرائها.
     */
    private function pricedItem(float $salePrice = 1000): Item
    {
        return Item::factory()->stocked()->create(['sale_price' => $salePrice]);
    }
}
