<?php

namespace Tests\Feature\Admin\Purchases;

use App\Enums\ExchangeRateSource;
use App\Invoices\FetchDollarRate;
use App\Invoices\FetchMerchantsBankDollarRate;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseInvoiceExchangeRateApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: Who may change the yuan rate when creating an invoice, with the bank's rate and the rate typed (9), and what is saved.
     * AR: منو يكدر يغيّر سعر اليوان وقت إنشاء الفاتورة، ويا سعر البنك والسعر المكتوب (9)، وشنو ينحفظ.
     *
     * @return array<string, array{list<string>, ?float, float, ExchangeRateSource}>
     */
    public static function createCases(): array
    {
        return [
            'without the permission, the bank rate is kept' => [
                ['invoice.view', 'invoice.create'], 6.7, 6.7, ExchangeRateSource::AgriculturalBankOfChina,
            ],
            'with the permission, the typed rate is kept as manual' => [
                ['invoice.view', 'invoice.create', 'invoice.edit_exchange_rate'], 6.7, 9.0, ExchangeRateSource::Manual,
            ],
            'when no bank answers, anyone types it' => [
                ['invoice.view', 'invoice.create'], null, 9.0, ExchangeRateSource::Manual,
            ],
        ];
    }

    /**
     * EN: When an invoice is created through the admin API, only a user with the permission to edit the exchange rate changes the bank's rate:
     *     without it, the bank's rate fetched when the page opened is saved, even when another rate is sent. When no bank answers, anyone types it.
     * AR: لما تنعمل فاتورة من الـ API، بس اليوزر اللي عنده صلاحية تعديل سعر الصرف يغيّر سعر البنك:
     *     بدونها ينحفظ سعر البنك اللي انجاب لما انفتحت الصفحة، حتى لو انبعث سعر غيره. وإذا ولا بنك جاوب، أي واحد يكتبه.
     *
     * @param  list<string>  $permissions
     */
    #[DataProvider('createCases')]
    public function test_create_keeps_the_bank_rate_unless_the_user_may_change_it(array $permissions, ?float $bankRate, float $savedRate, ExchangeRateSource $savedSource): void
    {
        $this->mock(FetchDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn($bankRate));
        $this->mock(FetchMerchantsBankDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn(null));
        $this->actingAs($this->userWith($permissions));

        $this->getJson('/admin-api/purchase-invoices/create')->assertOk();
        $this->postJson('/admin-api/purchase-invoices', [
            'supplier_id' => Supplier::factory()->create()->id,
            'invoice_category_id' => InvoiceCategory::factory()->create()->id,
            'date' => '2026-11-02',
            'exchange_rate' => 9,
            'dinar_rate' => 170,
            'cbm_price' => 120,
            'items' => [[
                'specific_description_id' => Item::factory()->create()->specificDescription->id,
                'boxes_count' => 12,
                'pieces_per_packet' => 24,
                'boxes_per_carton' => 1,
                'carton_cbm' => 0.12,
                'unit_cost' => 3.5,
            ]],
        ])->assertCreated();

        $invoice = Invoice::query()->sole();
        $this->assertEqualsWithDelta($savedRate, $invoice->exchange_rate, 0.0001);
        $this->assertSame($savedSource, $invoice->exchange_rate_source);
    }

    /**
     * EN: Who may change the yuan rate (7, from the bank) of a saved invoice to 9, and what is saved.
     * AR: منو يكدر يغيّر سعر اليوان (7، من البنك) لفاتورة محفوظة إلى 9، وشنو ينحفظ.
     *
     * @return array<string, array{list<string>, float, ExchangeRateSource}>
     */
    public static function editCases(): array
    {
        return [
            'with the permission, the new rate is saved as manual' => [
                ['invoice.view', 'invoice.update', 'invoice.edit_exchange_rate'], 9.0, ExchangeRateSource::Manual,
            ],
            'without the permission, the rate stays' => [
                ['invoice.view', 'invoice.update'], 7.0, ExchangeRateSource::AgriculturalBankOfChina,
            ],
        ];
    }

    /**
     * EN: After the invoice is saved, only a user with the permission to edit the exchange rate changes it, and the changed rate is marked as typed by hand.
     * AR: بعد ما تنحفظ الفاتورة، بس اليوزر اللي عنده صلاحية تعديل سعر الصرف يغيّره، والسعر المتغيّر يتعلّم إنه مكتوب باليد.
     *
     * @param  list<string>  $permissions
     */
    #[DataProvider('editCases')]
    public function test_update_changes_the_rate_only_with_the_permission(array $permissions, float $savedRate, ExchangeRateSource $savedSource): void
    {
        $invoice = Invoice::factory()->purchase()->create([
            'invoice_category_id' => InvoiceCategory::factory(),
            'exchange_rate' => 7,
            'exchange_rate_source' => ExchangeRateSource::AgriculturalBankOfChina,
            'dinar_rate' => 170,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => 10, 'pieces_per_packet' => 24, 'boxes_per_carton' => 1, 'carton_cbm' => 0.05, 'unit_cost' => 3.5, 'price' => 840]);
        $this->actingAs($this->userWith($permissions));

        $form = $this->getJson("/admin-api/purchase-invoices/{$invoice->id}")->assertOk()->json('data');
        $this->putJson("/admin-api/purchase-invoices/{$invoice->id}", [
            ...collect($form)->only(['supplier_id', 'invoice_category_id', 'date', 'dinar_rate', 'cbm_price'])->all(),
            'exchange_rate' => 9,
            'items' => $form['items'],
        ])->assertOk();

        $this->assertEqualsWithDelta($savedRate, $invoice->fresh()->exchange_rate, 0.0001);
        $this->assertSame($savedSource, $invoice->fresh()->exchange_rate_source);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);
        }

        return $user;
    }
}
