<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Models\InvoiceCategory;
use App\Models\Item;
use App\Models\Payoff;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CreatePurchaseInvoiceInitialPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The initial payment typed on the purchase invoice create page is saved as one payoff marked as the initial payment,
     *     with the typed amount and the invoice's date.
     * AR: الدفعة الأولية اللي تنكتب بصفحة إنشاء فاتورة الشراء تنحفظ تسديد واحد معلّم إنه دفعة أولية،
     *     بالمبلغ المكتوب وبتاريخ الفاتورة.
     */
    public function test_initial_payment_on_the_create_page_is_saved_as_a_marked_payoff(): void
    {
        Storage::fake('public');
        // The create page asks the banks for the exchange rate when it opens; the test never goes out to them.
        Process::fake();
        Http::fake();
        $undoRepeaterFake = Repeater::fake();
        $this->actingAs(User::factory()->create(['is_super' => true]));

        Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm([
                'name' => 'Sunglasses order',
                'supplier_id' => Supplier::factory()->create()->id,
                'invoice_category_id' => InvoiceCategory::factory()->create()->id,
                'date' => '2026-09-18',
                'exchange_rate' => 6.7,
                'cbm_price' => 120,
                'initial_payment' => 300000,
                'items' => [[
                    'specific_description_id' => Item::factory()->create()->specificDescription->id,
                    'boxes_count' => 12,
                    'box_quantity' => 24,
                    'boxes_per_carton' => 1,
                    'carton_cbm' => 0.12,
                    'unit_cost' => 3500,
                ]],
                'images' => [UploadedFile::fake()->image('invoice.jpg')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $payoff = Payoff::query()->sole();
        $this->assertTrue($payoff->is_initial_payment);
        $this->assertEquals(300000, $payoff->amount);
        $this->assertSame('2026-09-18', $payoff->date->toDateString());
    }
}
