<?php

namespace Tests\Unit;

use App\Invoices\DistributeSupplierPayoff;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DistributeSupplierPayoffTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The amount is split on the supplier's purchase invoices from the oldest to the newest, each getting up to its remaining.
     * AR: المبلغ يتوزع على فواتير شراء المورد من الأقدم للأحدث، وكل فاتورة تاخذ لحد الباقي عليها.
     */
    public function test_amount_is_split_from_the_oldest_invoice_to_the_newest(): void
    {
        $supplier = Supplier::factory()->create();
        $oldest = $this->purchaseInvoice($supplier, '2026-08-01', 1000);
        $newest = $this->purchaseInvoice($supplier, '2026-09-01', 500);
        $middle = $this->purchaseInvoice($supplier, '2026-08-15', 800);

        (new DistributeSupplierPayoff)->handle($this->superUser(), $supplier, 1500, '2026-09-10');

        $this->assertEquals(0, $oldest->fresh()->remaining);
        $this->assertEquals(300, $middle->fresh()->remaining);
        $this->assertEquals(500, $newest->fresh()->remaining);
        $this->assertSame(0, $newest->payoffs()->count());
    }

    /**
     * EN: An invoice that is already partly paid gets only what remains on it, and the rest goes to the next invoice.
     * AR: الفاتورة المسددة جزئياً تاخذ بس الباقي عليها، والزايد يروح للفاتورة اللي بعدها.
     */
    public function test_partly_paid_invoice_gets_only_its_remaining(): void
    {
        $supplier = Supplier::factory()->create();
        $older = $this->purchaseInvoice($supplier, '2026-08-01', 1000);
        Payoff::factory()->create(['invoice_id' => $older->id, 'amount' => 400]);
        $newer = $this->purchaseInvoice($supplier, '2026-09-01', 500);

        (new DistributeSupplierPayoff)->handle($this->superUser(), $supplier, 700, '2026-09-10');

        $this->assertEquals(0, $older->fresh()->remaining);
        $this->assertEquals(400, $newer->fresh()->remaining);
    }

    /**
     * EN: Paying exactly the whole debt pays every invoice of the supplier.
     * AR: تسديد الدين كله بالضبط يسدد كل فواتير المورد.
     */
    public function test_paying_the_whole_debt_pays_every_invoice(): void
    {
        $supplier = Supplier::factory()->create();
        $first = $this->purchaseInvoice($supplier, '2026-08-01', 1000);
        $second = $this->purchaseInvoice($supplier, '2026-09-01', 500);

        (new DistributeSupplierPayoff)->handle($this->superUser(), $supplier, 1500, '2026-09-10');

        $this->assertEquals(0, $first->fresh()->remaining);
        $this->assertEquals(0, $second->fresh()->remaining);
    }

    /**
     * EN: Invoices of other suppliers, fully paid invoices, and deleted invoices are skipped.
     * AR: فواتير الموردين الثانيين، والفواتير المسددة بالكامل، والفواتير المحذوفة ما تنسدد.
     */
    public function test_other_suppliers_fully_paid_and_deleted_invoices_are_skipped(): void
    {
        $supplier = Supplier::factory()->create();
        $otherSuppliersInvoice = $this->purchaseInvoice(Supplier::factory()->create(), '2026-07-01', 1000);
        $fullyPaid = $this->purchaseInvoice($supplier, '2026-07-05', 300);
        Payoff::factory()->create(['invoice_id' => $fullyPaid->id, 'amount' => 300]);
        $deleted = $this->purchaseInvoice($supplier, '2026-07-10', 200);
        $deleted->delete();
        $open = $this->purchaseInvoice($supplier, '2026-08-01', 1000);

        (new DistributeSupplierPayoff)->handle($this->superUser(), $supplier, 600, '2026-09-10');

        $this->assertEquals(400, $open->fresh()->remaining);
        $this->assertSame(0, $otherSuppliersInvoice->payoffs()->count());
        $this->assertSame(1, $fullyPaid->payoffs()->count());
        $this->assertSame(0, Payoff::query()->where('invoice_id', $deleted->id)->count());
    }

    /**
     * EN: Every payoff created gets the date entered.
     * AR: كل تسديد ينخلق ياخذ التاريخ المدخل.
     */
    public function test_every_payoff_gets_the_date_entered(): void
    {
        $supplier = Supplier::factory()->create();
        $this->purchaseInvoice($supplier, '2026-08-01', 1000);
        $this->purchaseInvoice($supplier, '2026-09-01', 500);

        (new DistributeSupplierPayoff)->handle($this->superUser(), $supplier, 1200, '2026-09-10');

        $this->assertSame(['2026-09-10', '2026-09-10'], Payoff::query()->pluck('date')->map->toDateString()->all());
    }

    /**
     * EN: An amount more than the supplier's whole debt is rejected, and no payoff is saved.
     * AR: المبلغ الأكثر من دين المورد كله ينرفض، وما ينحفظ ولا تسديد.
     */
    public function test_amount_more_than_the_debt_is_rejected_and_nothing_is_saved(): void
    {
        $supplier = Supplier::factory()->create();
        $this->purchaseInvoice($supplier, '2026-08-01', 1000);
        $this->purchaseInvoice($supplier, '2026-09-01', 500);

        try {
            (new DistributeSupplierPayoff)->handle($this->superUser(), $supplier, 1501, '2026-09-10');
            $this->fail('An amount more than the debt must be rejected.');
        } catch (ValidationException) {
            //
        }

        $this->assertSame(0, Payoff::query()->count());
    }

    /**
     * EN: The amount must be more than zero.
     * AR: المبلغ لازم يكون أكبر من صفر.
     */
    public function test_amount_must_be_more_than_zero(): void
    {
        $supplier = Supplier::factory()->create();
        $this->purchaseInvoice($supplier, '2026-08-01', 1000);

        $this->expectException(ValidationException::class);

        (new DistributeSupplierPayoff)->handle($this->superUser(), $supplier, 0, '2026-09-10');
    }

    /**
     * EN: A user without the payoff.create permission cannot pay, and no payoff is saved.
     * AR: اليوزر اللي ما عنده صلاحية payoff.create ما يكدر يسدد، وما ينحفظ ولا تسديد.
     */
    public function test_user_without_the_payoff_create_permission_cannot_pay(): void
    {
        $supplier = Supplier::factory()->create();
        $this->purchaseInvoice($supplier, '2026-08-01', 1000);

        try {
            (new DistributeSupplierPayoff)->handle(User::factory()->create(), $supplier, 500, '2026-09-10');
            $this->fail('A user without the permission must not pay.');
        } catch (AuthorizationException) {
            //
        }

        $this->assertSame(0, Payoff::query()->count());
    }

    /**
     * EN: A normal user with the payoff.create permission can pay.
     * AR: اليوزر العادي اللي عنده صلاحية payoff.create يكدر يسدد.
     */
    public function test_user_with_the_payoff_create_permission_can_pay(): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => 'payoff.create']);
        $supplier = Supplier::factory()->create();
        $invoice = $this->purchaseInvoice($supplier, '2026-08-01', 1000);

        (new DistributeSupplierPayoff)->handle($user, $supplier, 500, '2026-09-10');

        $this->assertEquals(500, $invoice->fresh()->remaining);
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
     * EN: Creates a pending purchase invoice of the supplier on the given date, with one line of the given price.
     * AR: ينشئ فاتورة شراء معلّقة للمورد بالتاريخ المحدد، بيها بند واحد بالسعر المحدد.
     */
    private function purchaseInvoice(Supplier $supplier, string $date, float $price): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create(['supplier_id' => $supplier->id, 'date' => $date]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => Item::factory()->create()->specificDescription->id,
            'boxes_count' => 1,
            'pieces_per_packet' => 10,
            'price' => $price,
        ]);

        return $invoice->fresh();
    }
}
