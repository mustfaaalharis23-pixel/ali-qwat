<?php

namespace Tests\Feature;

use App\Invoices\FetchDollarRate;
use App\Invoices\FetchMerchantsBankDollarRate;
use App\Models\InvoiceCategory;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class CreatePurchaseInvoiceInitialPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The initial payment typed on a new purchase invoice is saved as one payoff marked as the initial payment,
     *     with the typed amount and the invoice's date.
     * AR: الدفعة الأولية اللي تنكتب بفاتورة شراء جديدة تنحفظ تسديد واحد معلّم إنه دفعة أولية،
     *     بالمبلغ المكتوب وبتاريخ الفاتورة.
     */
    public function test_initial_payment_on_a_new_invoice_is_saved_as_a_marked_payoff(): void
    {
        Storage::fake('public');
        // The new invoice asks the banks for the exchange rate when it opens; the test never goes out to them.
        $this->mock(FetchDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn(6.7));
        $this->mock(FetchMerchantsBankDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn(null));
        $this->actingAs(User::factory()->create(['is_active' => true, 'is_super' => true]));
        $this->getJson('/admin-api/purchase-invoices/create')->assertOk();

        $this->postJson('/admin-api/purchase-invoices', [
            'name' => 'Sunglasses order',
            'supplier_id' => Supplier::factory()->create()->id,
            'invoice_category_id' => InvoiceCategory::factory()->create()->id,
            'date' => '2026-09-18',
            'exchange_rate' => 6.7,
            'dinar_rate' => 170,
            'cbm_price' => 120,
            'initial_payment' => 300000,
            'items' => [[
                'specific_description_id' => Item::factory()->create()->specificDescription->id,
                'entry_unit' => 'packets',
                'boxes_count' => 12,
                'pieces_per_packet' => 24,
                'boxes_per_carton' => 1,
                'carton_cbm' => 0.12,
                'unit_cost' => 3500,
                'groups' => [],
                'colors' => [],
            ]],
        ])->assertCreated();

        $payoff = Payoff::query()->sole();
        $this->assertTrue($payoff->is_initial_payment);
        $this->assertEquals(300000, $payoff->amount);
        $this->assertSame('2026-09-18', $payoff->date->toDateString());
    }
}
