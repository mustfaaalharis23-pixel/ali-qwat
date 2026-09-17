<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\SpecificDescription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayoffTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The required columns: invoice, amount, and date.
     * AR: الأعمدة الإجبارية: الفاتورة، المبلغ، والتاريخ.
     *
     * @return array<string, array{string}>
     */
    public static function requiredColumns(): array
    {
        return [
            'invoice_id' => ['invoice_id'],
            'amount' => ['amount'],
            'date' => ['date'],
        ];
    }

    /**
     * EN: A payoff cannot be saved in the database without an invoice, an amount, or a date.
     * AR: التسديد ما ينحفظ بالداتابيس بدون فاتورة أو مبلغ أو تاريخ.
     */
    #[DataProvider('requiredColumns')]
    public function test_payoff_cannot_be_saved_without_a_required_column(string $column): void
    {
        $row = ['invoice_id' => Invoice::factory()->purchase()->create()->id, 'amount' => 100, 'date' => '2026-09-01'];
        unset($row[$column]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23502]');

        DB::table('payoffs')->insert($row);
    }

    /**
     * EN: A new payoff has no image by default.
     * AR: التسديد الجديد يكون بدون صورة افتراضياً.
     */
    public function test_new_payoff_has_no_image_by_default(): void
    {
        DB::table('payoffs')->insert([
            'invoice_id' => Invoice::factory()->purchase()->create()->id,
            'amount' => 100,
            'date' => '2026-09-01',
        ]);

        $this->assertDatabaseHas('payoffs', [
            'amount' => 100,
            'image' => null,
        ]);
    }

    /**
     * EN: A payoff updates the invoice's paid amount and remaining.
     * AR: التسديد يحدّث المبلغ المدفوع والباقي بالفاتورة.
     */
    public function test_payoff_updates_the_paid_amount_and_remaining(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);

        Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 300]);

        $invoice = $invoice->fresh();

        $this->assertEquals(300, $invoice->paid_amount);
        $this->assertEquals(700, $invoice->remaining);
    }

    /**
     * EN: Updating a payoff's amount recalculates the invoice.
     * AR: تعديل مبلغ التسديد يعيد حساب الفاتورة.
     */
    public function test_updating_a_payoff_recalculates_the_invoice(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $payoff = Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 300]);

        $payoff->update(['amount' => 450]);

        $invoice = $invoice->fresh();

        $this->assertEquals(450, $invoice->paid_amount);
        $this->assertEquals(550, $invoice->remaining);
    }

    /**
     * EN: Deleting a payoff and restoring it recalculates the invoice each time.
     * AR: حذف التسديد واسترجاعه يعيد حساب الفاتورة بكل مرة.
     */
    public function test_deleting_and_restoring_a_payoff_recalculates_the_invoice(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $payoff = Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 300]);

        $payoff->delete();

        $this->assertEquals(0, $invoice->fresh()->paid_amount);
        $this->assertEquals(1000, $invoice->fresh()->remaining);

        $payoff->restore();

        $this->assertEquals(300, $invoice->fresh()->paid_amount);
        $this->assertEquals(700, $invoice->fresh()->remaining);
    }

    /**
     * EN: The payoff amounts that are not allowed: zero and negative.
     * AR: مبالغ التسديد الممنوعة: صفر وسالب.
     *
     * @return array<string, array{float}>
     */
    public static function notPositiveAmounts(): array
    {
        return [
            'zero' => [0],
            'negative' => [-100],
        ];
    }

    /**
     * EN: A payoff of zero or a negative amount is rejected, and the invoice stays unpaid.
     * AR: التسديد بمبلغ صفر أو سالب ينرفض، والفاتورة تبقى بدون تسديد.
     */
    #[DataProvider('notPositiveAmounts')]
    public function test_payoff_amount_must_be_more_than_zero(float $amount): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);

        try {
            Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => $amount]);
            $this->fail('A payoff of zero or a negative amount must be rejected.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(0, Payoff::withTrashed()->where('invoice_id', $invoice->id)->count());
        $this->assertEquals(0, $invoice->fresh()->paid_amount);
        $this->assertEquals(1000, $invoice->fresh()->remaining);
    }

    /**
     * EN: The payoffs of an invoice cannot add up to more than its amount.
     * AR: مجموع تسديدات الفاتورة ما يتجاوز مبلغها.
     */
    public function test_payoffs_cannot_exceed_the_invoice_amount(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 800]);

        $this->expectException(ValidationException::class);

        Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 300]);
    }

    /**
     * EN: A general invoice cannot have payoffs.
     * AR: الفاتورة العامة ما إلها تسديدات.
     */
    public function test_general_invoice_cannot_have_payoffs(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $invoice->general_description_id])->id,
            'price' => 500,
        ]);

        $this->expectException(ValidationException::class);

        Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 100]);
    }

    /**
     * EN: A purchase invoice that has payoffs cannot be deleted.
     * AR: فاتورة الشراء اللي عليها تسديدات ما تنحذف.
     */
    public function test_purchase_invoice_with_payoffs_cannot_be_deleted(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 300]);

        try {
            $invoice->delete();
            $this->fail('A purchase invoice with payoffs must not be deleted.');
        } catch (ValidationException) {
            //
        }

        $this->assertNotSoftDeleted($invoice);
    }

    /**
     * EN: A payoff cannot be moved to another invoice; both invoices keep their paid amounts.
     * AR: التسديد ما ينتقل لفاتورة ثانية، والفاتورتين يبقى مدفوعهن مثل ما هو.
     */
    public function test_payoff_cannot_be_moved_to_another_invoice(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $anotherInvoice = $this->purchaseInvoiceOf(1000);
        $payoff = Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 300]);

        try {
            $payoff->update(['invoice_id' => $anotherInvoice->id]);
            $this->fail('A payoff must not be moved to another invoice.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame($invoice->id, $payoff->fresh()->invoice_id);
        $this->assertEquals(300, $invoice->fresh()->paid_amount);
        $this->assertEquals(0, $anotherInvoice->fresh()->paid_amount);
    }

    /**
     * EN: A deleted invoice cannot get a payoff.
     * AR: الفاتورة المحذوفة ما ينضافلها تسديد.
     */
    public function test_deleted_invoice_cannot_get_a_payoff(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);
        $invoice->delete();

        try {
            Payoff::factory()->create(['invoice_id' => $invoice->id, 'amount' => 300]);
            $this->fail('A deleted invoice must not get a payoff.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(0, Payoff::withTrashed()->where('invoice_id', $invoice->id)->count());
    }

    /**
     * EN: Creates a pending purchase invoice with one line of the given price.
     * AR: ينشئ فاتورة شراء معلّقة بيها بند واحد بالسعر المحدد.
     */
    private function purchaseInvoiceOf(float $amount): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create();
        $this->purchaseLine($invoice, $amount);

        return $invoice;
    }

    /**
     * EN: Creates a purchase line of one box of 10 for a new item, with the given price.
     * AR: ينشئ بند شراء كارتون واحد بيه 10 لمادة جديدة، بالسعر المحدد.
     */
    private function purchaseLine(Invoice $invoice, float $price): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'box_quantity' => 10,
            'price' => $price,
        ]);
    }
}
