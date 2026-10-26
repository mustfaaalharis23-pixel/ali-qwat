<?php

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Invoices\ReturnInvoiceItems;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnInvoiceItemsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A return creates a pending invoice linked to the original, with the original's name, type, descriptions, supplier, and images, made by the user.
     * AR: الإرجاع ينشئ فاتورة معلّقة مربوطة بالأصلية، بنفس اسمها ونوعها ووصفها ومورّدها وصورها، والمنشئ هو اليوزر.
     */
    public function test_return_creates_a_pending_invoice_linked_to_the_original(): void
    {
        $user = $this->superUser();
        $line = $this->confirmedPurchaseLine();
        $original = $line->invoice;

        $return = (new ReturnInvoiceItems)->handle($user, $original, [$line->id => 4])->fresh();

        $this->assertSame($original->id, $return->original_invoice_id);
        $this->assertSame(InvoiceStatus::Pending, $return->status);
        $this->assertSame($original->name, $return->name);
        $this->assertSame($original->type, $return->type);
        $this->assertSame($original->general_description_id, $return->general_description_id);
        $this->assertSame($original->supplier_id, $return->supplier_id);
        $this->assertSame($original->images, $return->images);
        $this->assertSame($user->id, $return->user_id);
    }

    /**
     * EN: The return line has a negative quantity and a negative price (unit price × quantity), and the return is fully paid.
     * AR: بند الإرجاع كميته سالبة وسعره سالب (سعر الوحدة × الكمية)، والإرجاع ينحسب مدفوع بالكامل.
     */
    public function test_return_line_has_a_negative_quantity_and_price_and_the_return_is_fully_paid(): void
    {
        $line = $this->confirmedPurchaseLine();

        $return = (new ReturnInvoiceItems)->handle($this->superUser(), $line->invoice, [$line->id => 4])->fresh();
        $returnLine = $return->items()->sole();

        $this->assertSame($line->item_id, $returnLine->item_id);
        $this->assertEquals(-4, $returnLine->quantity);
        $this->assertEquals(-400, $returnLine->price);
        $this->assertEquals(-400, $return->amount);
        $this->assertEquals(-400, $return->paid_amount);
        $this->assertEquals(0, $return->remaining);
    }

    /**
     * EN: Confirming a return reduces the item's stock.
     * AR: تأكيد الإرجاع ينقص مخزون المادة.
     */
    public function test_confirming_a_return_reduces_the_stock(): void
    {
        $line = $this->confirmedPurchaseLine();
        $return = (new ReturnInvoiceItems)->handle($this->superUser(), $line->invoice, [$line->id => 4]);

        (new ConfirmPurchaseInvoice)->handle($this->superUser(), $return, -400);

        $this->assertEquals(20, $line->item->fresh()->available_quantity);
    }

    /**
     * EN: The returned quantity cannot be more than the line quantity minus what was returned before.
     * AR: الكمية المرجعة ما تتجاوز كمية البند ناقص اللي انرجع منه قبل.
     */
    public function test_cannot_return_more_than_the_quantity_not_returned_yet(): void
    {
        $line = $this->confirmedPurchaseLine();
        (new ReturnInvoiceItems)->handle($this->superUser(), $line->invoice, [$line->id => 20]);

        $this->expectException(ValidationException::class);

        (new ReturnInvoiceItems)->handle($this->superUser(), $line->invoice, [$line->id => 5]);
    }

    /**
     * EN: Items cannot be returned from a pending invoice.
     * AR: ما ينرجع شي من فاتورة معلّقة.
     */
    public function test_items_cannot_be_returned_from_a_pending_invoice(): void
    {
        $invoice = Invoice::factory()->purchase()->create();
        $line = $this->purchaseLine($invoice);

        $this->expectException(ValidationException::class);

        (new ReturnInvoiceItems)->handle($this->superUser(), $invoice, [$line->id => 1]);
    }

    /**
     * EN: The returned quantity must be more than zero.
     * AR: الكمية المرجعة لازم تكون أكبر من صفر.
     */
    public function test_returned_quantity_must_be_more_than_zero(): void
    {
        $line = $this->confirmedPurchaseLine();

        $this->expectException(ValidationException::class);

        (new ReturnInvoiceItems)->handle($this->superUser(), $line->invoice, [$line->id => 0]);
    }

    /**
     * EN: A line of another invoice cannot be returned through this invoice.
     * AR: بند فاتورة ثانية ما ينرجع عن طريق هاي الفاتورة.
     */
    public function test_line_of_another_invoice_cannot_be_returned(): void
    {
        $line = $this->confirmedPurchaseLine();
        $lineOfAnotherInvoice = $this->confirmedPurchaseLine();

        $this->expectException(ValidationException::class);

        (new ReturnInvoiceItems)->handle($this->superUser(), $line->invoice, [$lineOfAnotherInvoice->id => 1]);
    }

    /**
     * EN: A user without the invoice.create permission cannot return items.
     * AR: اليوزر اللي ما عنده صلاحية invoice.create ما يكدر يرجّع.
     */
    public function test_user_without_the_create_invoice_permission_cannot_return(): void
    {
        $line = $this->confirmedPurchaseLine();

        $this->expectException(AuthorizationException::class);

        (new ReturnInvoiceItems)->handle(User::factory()->create(), $line->invoice, [$line->id => 1]);
    }

    /**
     * EN: Creates a super user.
     * AR: ينشئ يوزر is_super.
     */
    private function superUser(): User
    {
        return User::factory()->create(['is_super' => true]);
    }

    /**
     * EN: Creates a confirmed purchase invoice with one line of 2 boxes of 12 for 2400 (unit price 100).
     * AR: ينشئ فاتورة شراء مؤكدة بيها بند واحد: كارتونين بيهن 12 بسعر 2400 (سعر الوحدة 100).
     */
    private function confirmedPurchaseLine(): InvoiceItem
    {
        return $this->purchaseLine(Invoice::factory()->purchase()->confirmed()->create());
    }

    /**
     * EN: Creates a purchase line of 2 boxes of 12 for 2400 for a new item.
     * AR: ينشئ بند شراء كارتونين بيهن 12 بسعر 2400 لمادة جديدة.
     */
    private function purchaseLine(Invoice $invoice): InvoiceItem
    {
        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 2,
            'pieces_per_packet' => 12,
            'price' => 2400,
        ]);
    }
}
