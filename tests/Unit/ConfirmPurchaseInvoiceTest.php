<?php

namespace Tests\Unit;

use App\Enums\InvoiceConfirmation;
use App\Enums\InvoiceStatus;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Invoices\RevertInvoiceConfirmation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConfirmPurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The importer's confirmation sets the invoice to confirmed by the importer, and saves who confirmed it.
     * AR: تأكيد المستورد يخلي الفاتورة مؤكدة من المستورد، ويحفظ منو أكدها.
     */
    public function test_importer_confirmation_sets_the_status_and_the_importer(): void
    {
        $importer = $this->userWith('invoice.confirm_as_importer');
        $invoice = $this->purchaseInvoice();

        (new ConfirmPurchaseInvoice)->handle($importer, $invoice, 1000, InvoiceConfirmation::Importer);

        $this->assertSame(InvoiceStatus::ImporterConfirmed, $invoice->fresh()->status);
        $this->assertSame($importer->id, $invoice->fresh()->importer_checker_id);
    }

    /**
     * EN: The receiver's confirmation, after the importer's, confirms the invoice and saves who confirmed it.
     * AR: تأكيد المستلم، بعد تأكيد المستورد، يأكد الفاتورة ويحفظ منو أكدها.
     */
    public function test_receiver_confirmation_confirms_the_invoice(): void
    {
        $receiver = $this->userWith('invoice.confirm_as_receiver');
        $invoice = $this->purchaseInvoice(importerConfirmed: true);

        (new ConfirmPurchaseInvoice)->handle($receiver, $invoice, 1000, InvoiceConfirmation::Receiver);

        $this->assertSame(InvoiceStatus::Confirmed, $invoice->fresh()->status);
        $this->assertSame($receiver->id, $invoice->fresh()->receiver_checker_id);
    }

    /**
     * EN: The receiver cannot confirm before the importer.
     * AR: المستلم ما يكدر يأكد قبل المستورد.
     */
    public function test_receiver_cannot_confirm_before_the_importer(): void
    {
        $this->expectException(ValidationException::class);

        (new ConfirmPurchaseInvoice)->handle($this->superUser(), $this->purchaseInvoice(), 1000, InvoiceConfirmation::Receiver);
    }

    /**
     * EN: The entered amount must equal the invoice amount.
     * AR: المبلغ المُدخل لازم يساوي مبلغ الفاتورة.
     */
    public function test_entered_amount_must_equal_the_invoice_amount(): void
    {
        $this->expectException(ValidationException::class);

        (new ConfirmPurchaseInvoice)->handle($this->superUser(), $this->purchaseInvoice(), 900, InvoiceConfirmation::Importer);
    }

    /**
     * EN: The importer's permission does not confirm the receiver's stage.
     * AR: صلاحية المستورد ما تأكد مرحلة المستلم.
     */
    public function test_importer_permission_does_not_confirm_as_the_receiver(): void
    {
        $importer = $this->userWith('invoice.confirm_as_importer');
        $invoice = $this->purchaseInvoice(importerConfirmed: true);

        $this->expectException(AuthorizationException::class);

        (new ConfirmPurchaseInvoice)->handle($importer, $invoice, 1000, InvoiceConfirmation::Receiver);
    }

    /**
     * EN: A super user confirms both stages of the same invoice.
     * AR: السوبر يوزر يأكد المرحلتين لنفس الفاتورة.
     */
    public function test_super_user_confirms_both_stages(): void
    {
        $super = $this->superUser();
        $invoice = $this->purchaseInvoice();

        (new ConfirmPurchaseInvoice)->handle($super, $invoice, 1000, InvoiceConfirmation::Importer);
        (new ConfirmPurchaseInvoice)->handle($super, $invoice->fresh(), 1000, InvoiceConfirmation::Receiver);

        $this->assertSame(InvoiceStatus::Confirmed, $invoice->fresh()->status);
    }

    /**
     * EN: A super user reverts the last confirmation: a confirmed invoice goes back to the importer's stage.
     * AR: السوبر يوزر يتراجع عن آخر تأكيد: الفاتورة المؤكدة ترجع لمرحلة المستورد.
     */
    public function test_super_user_reverts_the_last_confirmation(): void
    {
        $invoice = $this->purchaseInvoice(confirmed: true);

        (new RevertInvoiceConfirmation)->handle($this->superUser(), $invoice);

        $this->assertSame(InvoiceStatus::ImporterConfirmed, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->receiver_checker_id);
    }

    /**
     * EN: Only a super user reverts a confirmation.
     * AR: التراجع عن التأكيد للسوبر يوزر بس.
     */
    public function test_only_a_super_user_reverts_a_confirmation(): void
    {
        $user = $this->userWith('invoice.confirm_as_receiver');

        $this->expectException(AuthorizationException::class);

        (new RevertInvoiceConfirmation)->handle($user, $this->purchaseInvoice(confirmed: true));
    }

    /**
     * EN: Creates a purchase invoice of 1000: one box of 10 for 1000.
     * AR: ينشئ فاتورة شراء بـ 1000: كارتون واحد بيه 10 بسعر 1000.
     */
    private function purchaseInvoice(bool $importerConfirmed = false, bool $confirmed = false): Invoice
    {
        $factory = Invoice::factory()->purchase();

        if ($importerConfirmed) {
            $factory = $factory->importerConfirmed();
        }

        if ($confirmed) {
            $factory = $factory->confirmed();
        }

        $invoice = $factory->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'box_quantity' => 10,
            'price' => 1000,
        ]);

        return $invoice->fresh();
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
