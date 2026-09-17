<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Enums\OrderPaymentType;
use App\Invoices\TransferCashierMoney;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SpecificDescription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransferCashierMoneyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A transfer creates an input invoice on the cashier, with no images, and one line of the amount under the cash transfer description.
     * AR: التحويل ينشئ فاتورة داخل على الكاشير، بدون صور، وبيها بند واحد بالمبلغ تحت وصف تحويل الكاش.
     */
    public function test_transfer_creates_an_input_invoice_with_one_cash_transfer_line(): void
    {
        $actor = $this->superUser();
        $cashier = User::factory()->cashier()->create();
        $this->sale($cashier, OrderPaymentType::Cash, 1000);
        $description = SpecificDescription::query()->where('transfer_type', OrderPaymentType::Cash)->firstOrFail();

        $invoice = (new TransferCashierMoney)->handle($actor, $cashier, 400, '2026-09-10', 'Evening shift')->fresh();

        $this->assertSame(InvoiceType::Input, $invoice->type);
        $this->assertSame($description->general_description_id, $invoice->general_description_id);
        $this->assertSame($cashier->id, $invoice->cashier_id);
        $this->assertSame($actor->id, $invoice->user_id);
        $this->assertSame('2026-09-10', $invoice->date->toDateString());
        $this->assertSame([], $invoice->images);
        $this->assertSame('Evening shift', $invoice->notes);
        $this->assertEquals(400, $invoice->amount);

        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame($description->id, $invoice->items()->first()->specific_description_id);
    }

    /**
     * EN: An amount more than the money left on the cashier is rejected, and nothing is saved. Only the cashier's own cash sales count,
     *     minus what was transferred before: another cashier's sales and postpaid sales are not counted.
     * AR: المبلغ الأكثر من الباقي على الكاشير ينرفض، وما ينحفظ شي. تنحسب بس مبيعات الكاشير نفسه الكاش،
     *     ناقص اللي تحوّل قبل: مبيعات كاشير ثاني ومبيعات الآجل ما تنحسب.
     */
    public function test_amount_more_than_the_money_left_on_the_cashier_is_rejected(): void
    {
        $actor = $this->superUser();
        $cashier = User::factory()->cashier()->create();
        $this->sale($cashier, OrderPaymentType::Cash, 1000);
        $this->sale($cashier, OrderPaymentType::Postpaid, 5000);
        $this->sale(User::factory()->cashier()->create(), OrderPaymentType::Cash, 5000);
        (new TransferCashierMoney)->handle($actor, $cashier, 800, '2026-09-10');

        try {
            (new TransferCashierMoney)->handle($actor, $cashier, 201, '2026-09-11');
            $this->fail('An amount more than the money left on the cashier must be rejected.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(1, Invoice::query()->revenues()->count());
    }

    /**
     * EN: Each user is missing one of the two permissions a transfer needs.
     * AR: كل يوزر ناقصته وحدة من الصلاحيتين اللي يحتاجهن التحويل.
     *
     * @return array<string, array{list<string>}>
     */
    public static function usersMissingOnePermission(): array
    {
        return [
            'without the transfer permission' => [['invoice.create']],
            'without invoice.create' => [['financial_dashboard.transfer_cashier_money']],
        ];
    }

    /**
     * EN: A user needs both the transfer permission and invoice.create; missing one of them rejects the transfer, and nothing is saved.
     * AR: اليوزر يحتاج صلاحية التحويل و invoice.create سوية؛ إذا ناقصته وحدة ينرفض التحويل، وما ينحفظ شي.
     *
     * @param  list<string>  $permissions
     */
    #[DataProvider('usersMissingOnePermission')]
    public function test_user_needs_both_permissions_to_transfer(array $permissions): void
    {
        $actor = User::factory()->create();
        DB::table('user_permissions')->insert(array_map(
            fn (string $permission): array => ['user_id' => $actor->id, 'permission' => $permission],
            $permissions,
        ));
        $cashier = User::factory()->cashier()->create();
        $this->sale($cashier, OrderPaymentType::Cash, 1000);

        try {
            (new TransferCashierMoney)->handle($actor, $cashier, 500, '2026-09-10');
            $this->fail('A user missing a permission must not transfer.');
        } catch (AuthorizationException) {
            //
        }

        $this->assertSame(0, Invoice::query()->revenues()->count());
    }

    /**
     * EN: Creates an order of the payment type sold by the cashier, with one line of the given amount.
     * AR: ينشئ أوردر بطريقة الدفع المحددة باعه الكاشير، بيه بند واحد بالمبلغ المحدد.
     */
    private function sale(User $cashier, OrderPaymentType $paymentType, float $amount): void
    {
        $order = Order::factory()->create(['cashier_id' => $cashier->id, 'payment_type' => $paymentType]);

        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'price' => $amount]);
    }

    /**
     * EN: Creates a super user.
     * AR: ينشئ يوزر is_super.
     */
    private function superUser(): User
    {
        return User::factory()->create(['is_super' => true]);
    }
}
