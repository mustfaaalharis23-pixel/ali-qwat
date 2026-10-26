<?php

namespace Tests\Unit;

use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceStatus;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImporterConfirmationOnCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Creating a purchase invoice confirms it for the importer at once: whoever created it is its importer, and the moment is saved. No amount is asked for.
     * AR: خلق فاتورة الشراء يأكدها من المستورد فوراً: اللي سواها هو مستوردها، ووقت التأكيد ينحفظ. وماكو مبلغ ينطلب.
     */
    public function test_creating_a_purchase_invoice_confirms_it_as_the_importer(): void
    {
        $creator = User::factory()->create();
        $invoice = Invoice::factory()->purchase()->create(['user_id' => $creator->id]);

        (new ConfirmPurchaseInvoice)->handleOnCreation($invoice);

        $invoice = $invoice->fresh();

        $this->assertSame(InvoiceStatus::ImporterConfirmed, $invoice->status);
        $this->assertSame($creator->id, $invoice->importer_checker_id);
        $this->assertNotNull($invoice->importer_confirmed_at);
    }

    /**
     * EN: The automatic confirmation asks for no permission: creating the invoice is the confirmation itself, so a creator with no permission at all still confirms it.
     * AR: التأكيد التلقائي ما يطلب أي صلاحية: خلق الفاتورة هو نفسه التأكيد، فحتى منشئ بلا أي صلاحية تتأكد فاتورته.
     */
    public function test_the_automatic_confirmation_needs_no_permission(): void
    {
        $creator = User::factory()->create();
        $invoice = Invoice::factory()->purchase()->create(['user_id' => $creator->id]);

        (new ConfirmPurchaseInvoice)->handleOnCreation($invoice);

        $this->assertSame(InvoiceStatus::ImporterConfirmed, $invoice->fresh()->status);
    }

    /**
     * EN: A created purchase invoice waits for the receiver only, because the importer's side is already done.
     * AR: فاتورة الشراء المخلوقة تنتظر المستلم بس، لأن جهة المستورد صارت خلاص.
     */
    public function test_a_created_purchase_invoice_waits_for_the_receiver(): void
    {
        $invoice = Invoice::factory()->purchase()->create();

        (new ConfirmPurchaseInvoice)->handleOnCreation($invoice);

        $this->assertSame(InvoiceConfirmation::Receiver, $invoice->fresh()->awaitedConfirmation());
    }

    /**
     * EN: The receiver's confirmation still works after the automatic one, and it is what finishes the invoice.
     * AR: تأكيد المستلم بعده يشتغل بعد التلقائي، وهو اللي يخلص الفاتورة.
     */
    public function test_the_receiver_still_confirms_after_the_automatic_confirmation(): void
    {
        $invoice = $this->purchaseInvoiceOf(1000);

        (new ConfirmPurchaseInvoice)->handleOnCreation($invoice);
        (new ConfirmPurchaseInvoice)->handle(
            $this->userWith('invoice.confirm_as_receiver'),
            $invoice->fresh(),
            1000,
            InvoiceConfirmation::Receiver,
        );

        $this->assertSame(InvoiceStatus::Confirmed, $invoice->fresh()->status);
    }

    /**
     * EN: A general invoice is never touched by this: it has no importer and no stages, so its status stays as it was.
     * AR: الفاتورة العامة ما تنمس بهذا أبداً: ماكو بيها مستورد ولا مراحل، فحالتها تبقى مثل ما هي.
     */
    public function test_a_general_invoice_is_left_alone(): void
    {
        $invoice = Invoice::factory()->create();

        (new ConfirmPurchaseInvoice)->handleOnCreation($invoice);

        $invoice = $invoice->fresh();

        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertNull($invoice->importer_checker_id);
        $this->assertNull($invoice->importer_confirmed_at);
    }

    /**
     * EN: An invoice that is already past the importer's stage is not confirmed again, so its first importer and moment stay.
     * AR: الفاتورة اللي عدت مرحلة المستورد ما تتأكد من جديد، فمستوردها الأول ووقته يبقون.
     */
    public function test_an_already_confirmed_invoice_is_not_confirmed_again(): void
    {
        $invoice = Invoice::factory()->purchase()->importerConfirmed()->create();
        $firstImporter = $invoice->importer_checker_id;

        (new ConfirmPurchaseInvoice)->handleOnCreation($invoice);

        $this->assertSame($firstImporter, $invoice->fresh()->importer_checker_id);
    }

    /**
     * EN: Creates a purchase invoice of the given amount: one box of ten.
     * AR: ينشئ فاتورة شراء بالمبلغ المطلوب: كارتون واحد بيه عشرة.
     */
    private function purchaseInvoiceOf(float $amount): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 10,
            'price' => $amount,
        ]);

        return $invoice->fresh();
    }

    /**
     * EN: Creates a normal user with the given permission.
     * AR: ينشئ يوزر عادي عنده الصلاحية المحددة.
     */
    private function userWith(string $permission): User
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);

        return $user;
    }
}
