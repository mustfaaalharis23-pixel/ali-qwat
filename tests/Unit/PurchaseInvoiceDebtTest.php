<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseInvoiceDebtTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Given an invoice of 1000, when it gets two payoffs of 300 and 200, then it is paid 500 and 500 remains.
     * AR: فاتورة بـ 1000، ينضافلها تسديدين 300 و 200، فالمدفوع 500 والباقي 500.
     */
    public function test_paid_amount_is_the_sum_of_all_the_payoffs(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);

        $this->pay($invoice, 300);
        $this->pay($invoice, 200);

        $this->assertDebt($invoice, paid: 500, remaining: 500);
    }

    /**
     * EN: Given an invoice of 1000 with payoffs of 300 and 200, when the 200 is edited to 700, then it is fully paid.
     * AR: فاتورة بـ 1000 عليها تسديدين 300 و 200، يتعدّل الـ 200 إلى 700، فتصير مدفوعة بالكامل.
     */
    public function test_editing_one_of_the_payoffs_up_to_the_full_amount_recalculates_the_invoice(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 300);
        $payoff = $this->pay($invoice, 200);

        $payoff->update(['amount' => 700]);

        $this->assertDebt($invoice, paid: 1000, remaining: 0);
    }

    /**
     * EN: Given an invoice of 1000 with payoffs of 300 and 200, when the 200 is deleted, then only the 300 stays paid.
     * AR: فاتورة بـ 1000 عليها تسديدين 300 و 200، ينحذف الـ 200، فيبقى المدفوع 300 بس.
     */
    public function test_deleting_one_of_the_payoffs_keeps_the_others_paid(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 300);
        $payoff = $this->pay($invoice, 200);

        $payoff->delete();

        $this->assertDebt($invoice, paid: 300, remaining: 700);
    }

    /**
     * EN: Given an invoice of 1000 with payoffs of 300 and a deleted 200, when the 200 is restored, then it is paid again.
     * AR: فاتورة بـ 1000 عليها تسديد 300 وتسديد 200 محذوف، ينرجع الـ 200، فيرجع ينحسب بالمدفوع.
     */
    public function test_restoring_one_of_the_payoffs_adds_it_back_to_the_paid_amount(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 300);
        $payoff = $this->pay($invoice, 200);
        $payoff->delete();

        $payoff->restore();

        $this->assertDebt($invoice, paid: 500, remaining: 500);
    }

    /**
     * EN: Given an invoice of 1000 with payoffs of 300 and 200, when the 200 is force deleted, then only the 300 stays paid.
     * AR: فاتورة بـ 1000 عليها تسديدين 300 و 200، ينحذف الـ 200 نهائياً، فيبقى المدفوع 300 بس.
     */
    public function test_force_deleting_one_of_the_payoffs_keeps_the_others_paid(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 300);
        $payoff = $this->pay($invoice, 200);

        $payoff->forceDelete();

        $this->assertDebt($invoice, paid: 300, remaining: 700);
    }

    /**
     * EN: Given an invoice of 1000 with a payoff of 600, when a payoff of exactly the remaining 400 is added, then it is accepted and nothing remains.
     * AR: فاتورة بـ 1000 عليها تسديد 600، ينضاف تسديد بالباقي بالضبط 400، فينقبل وما يبقى شي.
     */
    public function test_paying_exactly_the_remaining_is_accepted_and_nothing_remains(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 600);

        $this->pay($invoice, 400);

        $this->assertDebt($invoice, paid: 1000, remaining: 0);
    }

    /**
     * EN: Given an invoice of 1000 with a payoff of 700, when a payoff of 400 is added, then it is rejected and the numbers stay as they were.
     * AR: فاتورة بـ 1000 عليها تسديد 700، ينضاف تسديد 400، فينرفض والأرقام تبقى مثل ما هي.
     */
    public function test_adding_a_payoff_larger_than_the_remaining_is_rejected(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 700);

        $this->assertRejected(fn () => $this->pay($invoice, 400));

        $this->assertSame(1, $invoice->payoffs()->count());
        $this->assertDebt($invoice, paid: 700, remaining: 300);
    }

    /**
     * EN: Given an invoice of 1000 with payoffs of 600 and 300, when the 300 is edited to 500, then it is rejected and the numbers stay as they were.
     * AR: فاتورة بـ 1000 عليها تسديدين 600 و 300، يتعدّل الـ 300 إلى 500، فينرفض والأرقام تبقى مثل ما هي.
     */
    public function test_editing_a_payoff_beyond_the_invoice_amount_is_rejected(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 600);
        $payoff = $this->pay($invoice, 300);

        $this->assertRejected(fn () => $payoff->update(['amount' => 500]));

        $this->assertEquals(300, $payoff->fresh()->amount);
        $this->assertDebt($invoice, paid: 900, remaining: 100);
    }

    /**
     * EN: Given an invoice of 1000 with a deleted payoff of 800 and a payoff of 300, when the 800 is restored, then it is rejected and stays deleted.
     * AR: فاتورة بـ 1000 عليها تسديد 800 محذوف وتسديد 300، ينرجع الـ 800، فينرفض ويبقى محذوف.
     */
    public function test_restoring_a_payoff_beyond_the_invoice_amount_is_rejected(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $payoff = $this->pay($invoice, 800);
        $payoff->delete();
        $this->pay($invoice, 300);

        $this->assertRejected(fn () => $payoff->restore());

        $this->assertSoftDeleted($payoff);
        $this->assertDebt($invoice, paid: 300, remaining: 700);
    }

    /**
     * EN: Given an invoice of 1000 with a payoff of 300, when its line price changes to 800, then the paid amount stays and only the remaining changes.
     * AR: فاتورة بـ 1000 عليها تسديد 300، يتغير سعر بندها إلى 800، فالمدفوع يبقى والباقي بس يتغير.
     */
    public function test_changing_a_line_price_keeps_the_paid_amount_and_changes_the_remaining(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 300);

        $invoice->items()->first()->update(['price' => 800]);

        $this->assertEquals(800, $invoice->fresh()->amount);
        $this->assertDebt($invoice, paid: 300, remaining: 500);
    }

    /**
     * EN: Given an invoice of 1000 with a payoff of 800, when its line price changes to 500, then it is rejected, because the remaining cannot be negative.
     * AR: فاتورة بـ 1000 عليها تسديد 800، يتغير سعر بندها إلى 500، فينرفض، لأن الباقي ما يصير سالب.
     */
    public function test_lowering_a_line_price_below_the_paid_amount_is_rejected(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 800);
        $line = $invoice->items()->first();

        $this->assertRejected(fn () => $line->update(['price' => 500]));

        $this->assertEquals(1000, $line->fresh()->price);
        $this->assertEquals(1000, $invoice->fresh()->amount);
        $this->assertDebt($invoice, paid: 800, remaining: 200);
    }

    /**
     * EN: Given an invoice of 1000 with a payoff of 800, when its line price changes to exactly 800, then it is accepted and nothing remains.
     * AR: فاتورة بـ 1000 عليها تسديد 800، يتغير سعر بندها إلى 800 بالضبط، فينقبل وما يبقى شي.
     */
    public function test_lowering_a_line_price_to_exactly_the_paid_amount_is_accepted(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $this->pay($invoice, 800);

        $invoice->items()->first()->update(['price' => 800]);

        $this->assertEquals(800, $invoice->fresh()->amount);
        $this->assertDebt($invoice, paid: 800, remaining: 0);
    }

    /**
     * EN: Given an invoice with lines of 600 and 400 and a payoff of 800, when the 400 line is deleted, then it is rejected, because the remaining cannot be negative.
     * AR: فاتورة ببندين 600 و 400 عليها تسديد 800، ينحذف بند الـ 400، فينرفض، لأن الباقي ما يصير سالب.
     */
    public function test_deleting_a_line_that_leaves_the_amount_below_the_paid_amount_is_rejected(): void
    {
        $invoice = $this->purchaseInvoiceOf(600);
        $line = $this->addLine($invoice, 400);
        $this->pay($invoice, 800);

        $this->assertRejected(fn () => $line->delete());

        $this->assertNotSoftDeleted($line);
        $this->assertEquals(1000, $invoice->fresh()->amount);
        $this->assertDebt($invoice, paid: 800, remaining: 200);
    }

    /**
     * EN: Creates a pending purchase invoice with one line of the given price.
     * AR: ينشئ فاتورة شراء معلّقة بيها بند واحد بالسعر المحدد.
     */
    private function purchaseInvoiceOf(float $amount): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create();
        $this->addLine($invoice, $amount);

        return $invoice;
    }

    /**
     * EN: Adds a purchase line of one box of 10 for a new item, with the given price.
     * AR: يضيف بند شراء كارتون واحد بيه 10 لمادة جديدة، بالسعر المحدد.
     */
    private function addLine(Invoice $invoice, float $price): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 10,
            'price' => $price,
        ]);
    }

    /**
     * EN: Adds a payoff of the given amount to the invoice.
     * AR: يضيف تسديد بالمبلغ المحدد للفاتورة.
     */
    private function pay(Invoice $invoice, float $amount): Payoff
    {
        return Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => $amount]);
    }

    /**
     * EN: Asserts the invoice's paid amount and remaining, as saved in the database.
     * AR: يتأكد من المدفوع والباقي بالفاتورة، مثل ما محفوظين بالداتابيس.
     */
    private function assertDebt(Invoice $invoice, float $paid, float $remaining): void
    {
        $invoice = $invoice->fresh();

        $this->assertEquals($paid, $invoice->paid_amount);
        $this->assertEquals($remaining, $invoice->remaining);
    }

    /**
     * EN: Asserts that the action is rejected with a validation error.
     * AR: يتأكد إن العملية تنرفض بخطأ تحقق.
     */
    private function assertRejected(Closure $action): void
    {
        try {
            $action();
            $this->fail('The action must be rejected.');
        } catch (ValidationException) {
            //
        }
    }
}
