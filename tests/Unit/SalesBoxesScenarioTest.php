<?php

namespace Tests\Unit;

use App\Enums\OrderPaymentType;
use App\Invoices\TransferCashierMoney;
use App\Metrics\Calculations\RevenuesTotal;
use App\Metrics\Period;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SpecificDescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesBoxesScenarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A day of work: two cashiers sell cash and postpaid to different customers, then the money is transferred.
     *     The cash transfer moves all the money on one cashier, and a postpaid withdrawal invoice moves part of the postpaid.
     *     Every transferred amount leaves its sales box and enters the accountant's box and the revenues, so the total money stays the same.
     *     A second transfer from the same cashier is rejected, because nothing is left on the cashier.
     * AR: يوم عمل: كاشيرين يبيعون كاش وآجل لزباين مختلفين، وبعدين تتحول الفلوس.
     *     تحويل الكاش ينقل كل المبلغ اللي على كاشير واحد، وفاتورة سحب آجل تنقل جزء من الآجل.
     *     كل مبلغ يتحوّل يطلع من صندوق مبيعاته ويدخل صندوق المحاسب والإيرادات، فمجموع الفلوس يبقى نفسه.
     *     تحويل ثاني من نفس الكاشير ينرفض، لأن ما بقى عليه شي.
     */
    public function test_transfers_move_money_from_the_sales_boxes_to_the_accountant(): void
    {
        $accountant = User::factory()->create(['is_super' => true]);
        $cashierA = User::factory()->cashier()->create();
        $cashierB = User::factory()->cashier()->create();
        $this->sale($cashierA, OrderPaymentType::Cash, 800000);
        $this->sale($cashierB, OrderPaymentType::Cash, 500000);
        $this->sale($cashierA, OrderPaymentType::Postpaid, 1200000);
        $this->sale($cashierB, OrderPaymentType::Postpaid, 300000);

        $this->assertMoney(cashBox: 1300000, postpaidBox: 1500000, onCashierA: 800000, onCashierB: 500000, accountant: 0);

        (new TransferCashierMoney)->handle($accountant, $cashierA, 800000, today());
        $this->postpaidWithdrawal($accountant, 700000);

        $this->assertMoney(cashBox: 500000, postpaidBox: 800000, onCashierA: 0, onCashierB: 500000, accountant: 1500000);
        $this->assertEquals(1500000, (new RevenuesTotal)->calculate($this->everyDate()));

        try {
            (new TransferCashierMoney)->handle($accountant, $cashierA, 1, today());
            $this->fail('A transfer from a cashier with nothing left must be rejected.');
        } catch (ValidationException) {
            //
        }

        $this->assertMoney(cashBox: 500000, postpaidBox: 800000, onCashierA: 0, onCashierB: 500000, accountant: 1500000);
    }

    /**
     * EN: Deleting a transfer invoice puts the money back: it returns to the cashier and the cash sales box, and leaves the accountant's box.
     *     After that, the same money can be transferred again.
     * AR: حذف فاتورة التحويل يرجّع الفلوس: ترجع على الكاشير وصندوق مبيعات الكاش، وتطلع من صندوق المحاسب.
     *     وبعدها نفس المبلغ يكدر يتحوّل مرة ثانية.
     */
    public function test_deleting_a_transfer_puts_the_money_back(): void
    {
        $accountant = User::factory()->create(['is_super' => true]);
        $cashierA = User::factory()->cashier()->create();
        $cashierB = User::factory()->cashier()->create();
        $this->sale($cashierA, OrderPaymentType::Cash, 800000);
        $this->sale($cashierB, OrderPaymentType::Cash, 500000);
        $transfer = (new TransferCashierMoney)->handle($accountant, $cashierA, 800000, today());

        $transfer->delete();

        $this->assertMoney(cashBox: 1300000, postpaidBox: 0, onCashierA: 800000, onCashierB: 500000, accountant: 0);

        (new TransferCashierMoney)->handle($accountant, $cashierA, 800000, today());

        $this->assertMoney(cashBox: 500000, postpaidBox: 0, onCashierA: 0, onCashierB: 500000, accountant: 800000);
    }

    /**
     * EN: Checks every box at once: the two sales boxes, the money on each cashier, and the accountant's box.
     * AR: يتأكد من كل الصناديق مرة وحدة: صندوقي المبيعات، واللي على كل كاشير، وصندوق المحاسب.
     */
    private function assertMoney(float $cashBox, float $postpaidBox, float $onCashierA, float $onCashierB, float $accountant): void
    {
        [$cashierA, $cashierB] = User::query()->where('is_cashier', true)->orderBy('id')->get()->all();
        $transfer = new TransferCashierMoney;

        $this->assertEquals($cashBox, Order::boxBalance(OrderPaymentType::Cash, $this->everyDate()), 'Cash sales box');
        $this->assertEquals($postpaidBox, Order::boxBalance(OrderPaymentType::Postpaid, $this->everyDate()), 'Postpaid sales box');
        $this->assertEquals($onCashierA, $transfer->moneyOn($cashierA), 'Money on cashier A');
        $this->assertEquals($onCashierB, $transfer->moneyOn($cashierB), 'Money on cashier B');
        $this->assertEquals($accountant, Invoice::accountantBalance($this->everyDate()), 'Accountant\'s box');
    }

    /**
     * EN: Creates an order of the payment type sold by the cashier to a new customer, with one line of the given amount.
     * AR: ينشئ أوردر بطريقة الدفع المحددة باعه الكاشير لزبون جديد، بيه بند واحد بالمبلغ المحدد.
     */
    private function sale(User $cashier, OrderPaymentType $paymentType, float $amount): void
    {
        $order = Order::factory()->create(['cashier_id' => $cashier->id, 'payment_type' => $paymentType]);

        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'price' => $amount]);
    }

    /**
     * EN: Creates a general invoice with one line under the postpaid transfer description, as the accountant enters it by hand.
     * AR: ينشئ فاتورة عامة بيها بند واحد تحت وصف تحويل الآجل، مثل ما يدخلها المحاسب بإيده.
     */
    private function postpaidWithdrawal(User $accountant, float $amount): void
    {
        $description = SpecificDescription::query()->where('transfer_type', OrderPaymentType::Postpaid)->firstOrFail();

        $invoice = Invoice::create([
            'name' => $description->name,
            'type' => $description->generalDescription->type,
            'general_description_id' => $description->general_description_id,
            'user_id' => $accountant->id,
            'date' => today(),
            'images' => [],
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $description->id,
            'price' => $amount,
        ]);
    }

    private function everyDate(): Period
    {
        return new Period(null, null);
    }
}
