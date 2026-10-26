<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTotalsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The supplier's total purchases are the sum of the amounts of its purchase invoices; other suppliers' and deleted invoices are not counted.
     * AR: مبلغ الشراء الكلي للمورد = مجموع مبالغ فواتير الشراء مالته، وفواتير الموردين الثانيين والمحذوفة ما تنحسب.
     */
    public function test_total_purchases_is_the_sum_of_the_supplier_invoice_amounts(): void
    {
        $supplier = Supplier::factory()->create();
        $this->purchaseInvoice($supplier, 1000);
        $this->purchaseInvoice($supplier, 500);
        $this->purchaseInvoice($supplier, 700)->delete();
        $this->purchaseInvoice(Supplier::factory()->create(), 9000);

        $this->assertEquals(1500, $supplier->totalPurchases());
    }

    /**
     * EN: The supplier's total paid is the sum of the payoffs on its purchase invoices.
     * AR: التسديد الكلي للمورد = مجموع التسديدات على فواتير الشراء مالته.
     */
    public function test_total_paid_is_the_sum_of_the_payoffs_on_the_supplier_invoices(): void
    {
        $supplier = Supplier::factory()->create();
        $first = $this->purchaseInvoice($supplier, 1000);
        $second = $this->purchaseInvoice($supplier, 500);
        Payoff::factory()->create(['invoice_id' => $first->id, 'amount' => 300]);
        Payoff::factory()->create(['invoice_id' => $first->id, 'amount' => 200]);
        Payoff::factory()->create(['invoice_id' => $second->id, 'amount' => 500]);

        $this->assertEquals(1000, $supplier->totalPaid());
    }

    /**
     * EN: The supplier's total debt is what remains on its purchase invoices: the total purchases minus the total paid.
     * AR: الدين الكلي للمورد = الباقي على فواتير الشراء مالته: مبلغ الشراء الكلي ناقص التسديد الكلي.
     */
    public function test_total_debt_is_what_remains_on_the_supplier_invoices(): void
    {
        $supplier = Supplier::factory()->create();
        $partlyPaid = $this->purchaseInvoice($supplier, 1000);
        $fullyPaid = $this->purchaseInvoice($supplier, 500);
        Payoff::factory()->create(['invoice_id' => $partlyPaid->id, 'amount' => 400]);
        Payoff::factory()->create(['invoice_id' => $fullyPaid->id, 'amount' => 500]);
        $this->purchaseInvoice(Supplier::factory()->create(), 9000);

        $this->assertEquals(600, $supplier->totalDebt());
        $this->assertEquals($supplier->totalPurchases() - $supplier->totalPaid(), $supplier->totalDebt());
    }

    /**
     * EN: The supplier's item count includes only its items that are not deleted.
     * AR: عدد مواد المورد يشمل بس المواد مالته غير المحذوفة.
     */
    public function test_items_count_includes_only_the_supplier_items_that_are_not_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        Item::factory()->count(3)->create(['supplier_id' => $supplier->id]);
        Item::factory()->create(['supplier_id' => $supplier->id])->delete();
        Item::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
        Item::factory()->create();

        $this->assertSame(3, $supplier->itemsCount());
    }

    /**
     * EN: A supplier without invoices or items has zero in every total.
     * AR: المورد اللي ما عنده فواتير ولا مواد، كل مجاميعه صفر.
     */
    public function test_supplier_without_invoices_or_items_has_zero_totals(): void
    {
        $supplier = Supplier::factory()->create();

        $this->assertEquals(0, $supplier->totalPurchases());
        $this->assertEquals(0, $supplier->totalPaid());
        $this->assertEquals(0, $supplier->totalDebt());
        $this->assertSame(0, $supplier->itemsCount());
    }

    /**
     * EN: Creates a pending purchase invoice of the supplier, with one line of the given price.
     * AR: ينشئ فاتورة شراء معلّقة للمورد، بيها بند واحد بالسعر المحدد.
     */
    private function purchaseInvoice(Supplier $supplier, float $price): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create(['supplier_id' => $supplier->id]);

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
