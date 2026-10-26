<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\SpecificDescription;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierDebtsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The total debt is the sum of what remains on the purchase invoices after their payoffs.
     * AR: الديون الكلية = مجموع الباقي على فواتير الشراء بعد تسديداتها.
     */
    public function test_total_debt_is_the_sum_of_the_remaining_on_purchase_invoices(): void
    {
        $supplier = Supplier::factory()->create();
        $this->purchaseInvoice($supplier, 1000);
        $partlyPaid = $this->purchaseInvoice($supplier, 500);
        Payoff::factory()->create(['invoice_id' => $partlyPaid->id, 'amount' => 200]);

        $this->assertEquals(1300, Invoice::totalDebt());
    }

    /**
     * EN: General invoices and deleted purchase invoices are not debts.
     * AR: الفواتير العامة وفواتير الشراء المحذوفة مو ديون.
     */
    public function test_general_invoices_and_deleted_purchase_invoices_are_not_debts(): void
    {
        $supplier = Supplier::factory()->create();
        $this->purchaseInvoice($supplier, 1000);
        $this->purchaseInvoice($supplier, 700)->delete();

        $general = Invoice::factory()->create(['type' => InvoiceType::Output]);
        InvoiceItem::factory()->create([
            'invoice_id' => $general->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $general->general_description_id])->id,
            'price' => 900,
        ]);

        $this->assertEquals(1000, Invoice::totalDebt());
    }

    /**
     * EN: Without debts, the total debt is zero and no supplier is listed.
     * AR: إذا ماكو ديون، الديون الكلية صفر وما يطلع ولا مورد.
     */
    public function test_without_debts_the_total_is_zero_and_no_supplier_is_listed(): void
    {
        $supplier = Supplier::factory()->create();
        $fullyPaid = $this->purchaseInvoice($supplier, 400);
        Payoff::factory()->create(['invoice_id' => $fullyPaid->id, 'amount' => 400]);

        $this->assertEquals(0, Invoice::totalDebt());
        $this->assertSame(['suppliers' => [], 'others' => 0.0], Invoice::debtBySupplier());
    }

    /**
     * EN: The debt of each supplier is the sum of its invoices, and the suppliers are listed from the largest debt to the smallest.
     * AR: دين كل مورد = مجموع فواتيره، والموردين يطلعون من الأكبر دين للأقل.
     */
    public function test_debt_of_each_supplier_is_listed_from_largest_to_smallest(): void
    {
        $small = Supplier::factory()->create(['name_ar' => 'Small']);
        $large = Supplier::factory()->create(['name_ar' => 'Large']);
        $middle = Supplier::factory()->create(['name_ar' => 'Middle']);
        $this->purchaseInvoice($small, 300);
        $this->purchaseInvoice($large, 600);
        $this->purchaseInvoice($large, 400);
        $this->purchaseInvoice($middle, 500);

        $this->assertEquals([
            'suppliers' => [
                ['name' => 'Large', 'debt' => 1000],
                ['name' => 'Middle', 'debt' => 500],
                ['name' => 'Small', 'debt' => 300],
            ],
            'others' => 0,
        ], Invoice::debtBySupplier());
    }

    /**
     * EN: With more than five suppliers in debt, the largest five are listed and the rest are added up as others.
     * AR: إذا الموردين المديونين أكثر من خمسة، يطلعون أكبر خمسة وباقيهم ينجمعون كـ "غيرهم".
     */
    public function test_more_than_five_suppliers_show_the_largest_five_and_the_rest_as_others(): void
    {
        foreach ([700, 600, 500, 400, 300, 200, 100] as $debt) {
            $this->purchaseInvoice(Supplier::factory()->create(['name_ar' => "Supplier {$debt}"]), $debt);
        }

        $debts = Invoice::debtBySupplier();

        $this->assertSame(['Supplier 700', 'Supplier 600', 'Supplier 500', 'Supplier 400', 'Supplier 300'], array_column($debts['suppliers'], 'name'));
        $this->assertEquals(300, $debts['others']);
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
