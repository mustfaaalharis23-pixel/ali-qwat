<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use App\Orders\PayCustomerDebt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutInvoiceByInvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Both payment types of a new order.
     * AR: نوعين الدفع للأوردر الجديد.
     *
     * @return array<string, array{OrderPaymentType}>
     */
    public static function paymentTypes(): array
    {
        return [
            'cash' => [OrderPaymentType::Cash],
            'postpaid' => [OrderPaymentType::Postpaid],
        ];
    }

    /**
     * EN: An invoice-by-invoice customer who still owes anything cannot take another order, cash or postpaid:
     *     the sale is rejected on the customer field and no new order is saved.
     * AR: الزبون اللي يشتغل فاتورة بفاتورة وعليه أي دين ما يكدر ياخذ أوردر ثاني، كاش أو آجل:
     *     البيعة تنرفض على حقل الزبون وما ينحفظ أوردر جديد.
     */
    #[DataProvider('paymentTypes')]
    public function test_invoice_by_invoice_customer_with_a_debt_cannot_take_another_order(OrderPaymentType $paymentType): void
    {
        $customer = Customer::factory()->create(['is_invoice_by_invoice' => true]);
        $this->existingPostpaidOrder($customer, amount: 500);

        try {
            (new Checkout)->handle(User::factory()->create(), $this->sale($customer, $paymentType));

            $this->fail('The sale was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
            $this->assertDatabaseCount('orders', 1);
        }
    }

    /**
     * EN: Once an invoice-by-invoice customer has paid the old order in full, their debt is zero and they can take a new order.
     * AR: من يسدد الزبون اللي يشتغل فاتورة بفاتورة أوردره القديم كامل، يصير دينه صفر ويكدر ياخذ أوردر جديد.
     */
    public function test_invoice_by_invoice_customer_with_no_debt_can_take_a_new_order(): void
    {
        $customer = Customer::factory()->create(['is_invoice_by_invoice' => true]);
        $this->existingPostpaidOrder($customer, amount: 500);
        (new PayCustomerDebt)->handle(User::factory()->create(['is_super' => true]), $customer, 500, '2026-10-20');

        (new Checkout)->handle(User::factory()->create(), $this->sale($customer, OrderPaymentType::Postpaid));

        $this->assertSame(2, $customer->orders()->count());
    }

    /**
     * EN: A customer who is not invoice-by-invoice can take a new order while still owing, and the money rolls over as usual.
     * AR: الزبون اللي مو فاتورة بفاتورة يكدر ياخذ أوردر جديد وهو بعده عليه دين، والفلوس تدور عادي.
     */
    public function test_customer_who_is_not_invoice_by_invoice_can_take_a_new_order_while_owing(): void
    {
        $customer = Customer::factory()->create(['is_invoice_by_invoice' => false]);
        $this->existingPostpaidOrder($customer, amount: 500);

        (new Checkout)->handle(User::factory()->create(), $this->sale($customer, OrderPaymentType::Postpaid));

        $this->assertSame(2, $customer->orders()->count());
        $this->assertEquals(1500, $customer->debt());
    }

    /**
     * EN: Builds a sale of one line worth 1000 for the customer, with the given payment type.
     * AR: يبني بيعة ببند واحد قيمته 1000 للزبون، بطريقة الدفع المحددة.
     */
    private function sale(Customer $customer, OrderPaymentType $paymentType): CheckoutData
    {
        $item = Item::factory()->stocked()->create(['sale_price' => 1000]);

        return new CheckoutData(
            customerId: $customer->id,
            paymentType: $paymentType,
            date: '2026-10-23',
            lines: [new CheckoutLine($item->id, quantity: 1)],
        );
    }

    /**
     * EN: Creates an older postpaid order of the customer, with one line of the given amount and nothing paid on it.
     * AR: ينشئ أوردر آجل أقدم للزبون، ببند واحد بالمبلغ المحدد وما مدفوع عليه شي.
     */
    private function existingPostpaidOrder(Customer $customer, float $amount): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'payment_type' => OrderPaymentType::Postpaid,
            'date' => '2026-10-01',
        ]);

        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'price' => $amount]);

        return $order->fresh();
    }
}
