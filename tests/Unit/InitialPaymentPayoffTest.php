<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InitialPaymentPayoffTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The changes that are not allowed on an initial payment: its amount, its date, and its image.
     * AR: التغييرات الممنوعة على الدفعة الأولية: مبلغها، تاريخها، وصورتها.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function changesToAnInitialPayment(): array
    {
        return [
            'amount' => [['amount' => 450]],
            'date' => [['date' => '2026-01-01']],
            'image' => [['image' => 'payoffs/receipt.jpg']],
        ];
    }

    /**
     * EN: The initial payment of a purchase invoice can never be edited: the change is rejected, and the payoff and the invoice stay as they were.
     * AR: الدفعة الأولية لفاتورة الشراء ما تتعدل أبداً: التغيير ينرفض، والتسديد والفاتورة يبقون مثل ما هم.
     *
     * @param  array<string, mixed>  $change
     */
    #[DataProvider('changesToAnInitialPayment')]
    public function test_initial_payment_cannot_be_edited(array $change): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $initialPayment = $this->initialPaymentOn($invoice, 300);

        try {
            $initialPayment->update($change);
            $this->fail('An initial payment must not be edited.');
        } catch (ValidationException) {
            //
        }

        $initialPayment = $initialPayment->fresh();
        $this->assertEquals(300, $initialPayment->amount);
        $this->assertSame($invoice->date->toDateString(), $initialPayment->date->toDateString());
        $this->assertNull($initialPayment->image);
        $this->assertEquals(300, $invoice->fresh()->paid_amount);
    }

    /**
     * EN: The initial payment can never be deleted on its own: the delete is rejected, it stays, and the invoice stays paid by it.
     * AR: الدفعة الأولية ما تنحذف لحالها أبداً: الحذف ينرفض، وتبقى، والفاتورة تبقى مسددة بيها.
     */
    public function test_initial_payment_cannot_be_deleted_on_its_own(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $initialPayment = $this->initialPaymentOn($invoice, 300);

        try {
            $initialPayment->delete();
            $this->fail('An initial payment must not be deleted on its own.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSoftDeleted($initialPayment);
        $this->assertEquals(300, $invoice->fresh()->paid_amount);
    }

    /**
     * EN: The initial payment can never be force deleted on its own either.
     * AR: الدفعة الأولية هم ما تنحذف نهائياً لحالها.
     */
    public function test_initial_payment_cannot_be_force_deleted_on_its_own(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $initialPayment = $this->initialPaymentOn($invoice, 300);

        try {
            $initialPayment->forceDelete();
            $this->fail('An initial payment must not be force deleted on its own.');
        } catch (ValidationException) {
            //
        }

        $this->assertDatabaseHas('payoffs', ['id' => $initialPayment->id, 'deleted_at' => null]);
    }

    /**
     * EN: A normal payoff on the same invoice can still be edited and deleted as before.
     * AR: التسديد العادي على نفس الفاتورة بعده يتعدل وينحذف مثل قبل.
     */
    public function test_a_normal_payoff_next_to_the_initial_payment_can_still_be_edited_and_deleted(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->initialPaymentOn($invoice, 300);
        $payoff = Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 200]);

        $payoff->update(['amount' => 250]);
        $this->assertEquals(550, $invoice->fresh()->paid_amount);

        $payoff->delete();
        $this->assertSoftDeleted($payoff);
        $this->assertEquals(300, $invoice->fresh()->paid_amount);
    }

    /**
     * EN: The initial payment goes only with its invoice: deleting an invoice whose only payoff is the initial payment deletes both.
     * AR: الدفعة الأولية تروح بس ويا فاتورتها: حذف فاتورة ما عليها غير الدفعة الأولية يحذف الاثنين.
     */
    public function test_deleting_the_invoice_deletes_its_initial_payment_with_it(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $initialPayment = $this->initialPaymentOn($invoice, 300);

        $invoice->delete();

        $this->assertSoftDeleted($invoice);
        $this->assertSoftDeleted($initialPayment);
    }

    /**
     * EN: An invoice that has a normal payoff besides the initial payment still cannot be deleted, and both payoffs stay.
     * AR: الفاتورة اللي عليها تسديد عادي غير الدفعة الأولية بعدها ما تنحذف، والتسديدين يبقون.
     */
    public function test_invoice_with_a_normal_payoff_besides_the_initial_payment_still_cannot_be_deleted(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $initialPayment = $this->initialPaymentOn($invoice, 300);
        $payoff = Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 200]);

        try {
            $invoice->delete();
            $this->fail('An invoice with a normal payoff must not be deleted.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSoftDeleted($invoice);
        $this->assertNotSoftDeleted($initialPayment);
        $this->assertNotSoftDeleted($payoff);
    }

    /**
     * EN: Restoring a deleted invoice brings its initial payment back, and the invoice is paid by it again.
     * AR: استرجاع الفاتورة المحذوفة يرجّع دفعتها الأولية، والفاتورة ترجع مسددة بيها.
     */
    public function test_restoring_the_invoice_restores_its_initial_payment(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $initialPayment = $this->initialPaymentOn($invoice, 300);
        $invoice->delete();

        $invoice->restore();

        $this->assertNotSoftDeleted($initialPayment);
        $this->assertEquals(300, $invoice->fresh()->paid_amount);
        $this->assertEquals(700, $invoice->fresh()->remaining);
    }

    /**
     * EN: Creates the initial payment of the invoice with the given amount, on the invoice's date.
     * AR: ينشئ الدفعة الأولية للفاتورة بالمبلغ المحدد، بتاريخ الفاتورة.
     */
    private function initialPaymentOn(Invoice $invoice, float $amount): Payoff
    {
        return Payoff::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'date' => $invoice->date,
            'is_initial_payment' => true,
        ]);
    }

    /**
     * EN: Creates a pending purchase invoice with one line of the given price.
     * AR: ينشئ فاتورة شراء معلّقة بيها بند واحد بالسعر المحدد.
     */
    private function purchaseInvoiceOf(float $amount): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'box_quantity' => 10,
            'price' => $amount,
        ]);

        return $invoice->fresh();
    }
}
