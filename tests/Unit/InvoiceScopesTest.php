<?php

namespace Tests\Unit;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceScopesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The expenses scope includes the output general invoices.
     * AR: سكوب المصاريف يشمل الفواتير العامة نوع خارج.
     */
    public function test_expenses_include_output_general_invoices(): void
    {
        $expense = Invoice::factory()->create(['type' => InvoiceType::Output]);

        $this->assertSame([$expense->id], Invoice::query()->expenses()->pluck('id')->all());
    }

    /**
     * EN: The expenses scope excludes input invoices.
     * AR: سكوب المصاريف ما يشمل فواتير الداخل.
     */
    public function test_expenses_exclude_input_invoices(): void
    {
        Invoice::factory()->create(['type' => InvoiceType::Input]);

        $this->assertSame(0, Invoice::query()->expenses()->count());
    }

    /**
     * EN: The expenses scope includes purchase invoices too.
     * AR: سكوب المصاريف يشمل فواتير الشراء هم.
     */
    public function test_expenses_include_purchase_invoices(): void
    {
        $purchase = Invoice::factory()->purchase()->create();

        $this->assertSame([$purchase->id], Invoice::query()->expenses()->pluck('id')->all());
    }

    /**
     * EN: The general expenses scope includes the output general invoices only: no purchase invoices and no input invoices.
     * AR: سكوب المصاريف العامة يشمل فواتير الخارج العامة بس: بدون فواتير الشراء وبدون فواتير الداخل.
     */
    public function test_general_expenses_are_output_invoices_without_purchase_invoices(): void
    {
        $expense = Invoice::factory()->create(['type' => InvoiceType::Output]);
        Invoice::factory()->purchase()->create();
        Invoice::factory()->create(['type' => InvoiceType::Input]);

        $this->assertSame([$expense->id], Invoice::query()->generalExpenses()->pluck('id')->all());
    }

    /**
     * EN: The purchase expenses scope includes the purchase invoices only: no output general invoices and no input invoices.
     * AR: سكوب مصاريف الشراء يشمل فواتير الشراء بس: بدون فواتير الخارج العامة وبدون فواتير الداخل.
     */
    public function test_purchase_expenses_are_purchase_invoices_only(): void
    {
        $purchase = Invoice::factory()->purchase()->create();
        Invoice::factory()->create(['type' => InvoiceType::Output]);
        Invoice::factory()->create(['type' => InvoiceType::Input]);

        $this->assertSame([$purchase->id], Invoice::query()->purchaseExpenses()->pluck('id')->all());
    }

    /**
     * EN: All the expenses are exactly the general expenses and the purchase expenses together: every expense is in one of them only.
     * AR: كل المصاريف هي بالضبط المصاريف العامة ومصاريف الشراء سوية: كل مصروف يكون بوحدة منهن بس.
     */
    public function test_expenses_are_the_general_expenses_and_the_purchase_expenses_together(): void
    {
        Invoice::factory()->count(2)->create(['type' => InvoiceType::Output]);
        Invoice::factory()->count(3)->purchase()->create();
        Invoice::factory()->create(['type' => InvoiceType::Input]);

        $general = Invoice::query()->generalExpenses()->pluck('id')->all();
        $purchase = Invoice::query()->purchaseExpenses()->pluck('id')->all();
        $all = Invoice::query()->expenses()->orderBy('id')->pluck('id')->all();
        $together = array_merge($general, $purchase);
        sort($together);

        $this->assertSame([], array_intersect($general, $purchase));
        $this->assertSame($all, $together);
    }

    /**
     * EN: The revenues scope includes the input invoices.
     * AR: سكوب الإيرادات يشمل فواتير الداخل.
     */
    public function test_revenues_include_input_invoices(): void
    {
        $revenue = Invoice::factory()->create(['type' => InvoiceType::Input]);

        $this->assertSame([$revenue->id], Invoice::query()->revenues()->pluck('id')->all());
    }

    /**
     * EN: The revenues scope excludes output invoices, general and purchase.
     * AR: سكوب الإيرادات ما يشمل فواتير الخارج، العامة والشراء.
     */
    public function test_revenues_exclude_output_invoices(): void
    {
        Invoice::factory()->create(['type' => InvoiceType::Output]);
        Invoice::factory()->purchase()->create();

        $this->assertSame(0, Invoice::query()->revenues()->count());
    }
}
