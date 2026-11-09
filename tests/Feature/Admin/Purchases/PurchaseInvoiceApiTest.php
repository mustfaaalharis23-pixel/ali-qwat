<?php

namespace Tests\Feature\Admin\Purchases;

use App\Enums\InvoiceStage;
use App\Enums\InvoiceStatus;
use App\Invoices\FetchDollarRate;
use App\Invoices\FetchMerchantsBankDollarRate;
use App\Models\Batch;
use App\Models\Color;
use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\Payoff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class PurchaseInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => $permission]);
        }

        return $user;
    }

    private function bankAnswers(?float $rate): void
    {
        $this->mock(FetchDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn($rate));
        $this->mock(FetchMerchantsBankDollarRate::class, fn (MockInterface $mock) => $mock->shouldReceive('handle')->andReturn(null));
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    private function payload(array $line = [], array $invoice = []): array
    {
        return [
            'supplier_id' => Supplier::factory()->create()->id,
            'invoice_category_id' => InvoiceCategory::factory()->create()->id,
            'date' => '2026-11-02',
            'exchange_rate' => 7,
            'dinar_rate' => 170,
            'cbm_price' => 120,
            'items' => [[
                'specific_description_id' => Item::factory()->create()->specificDescription->id,
                'entry_unit' => 'packets',
                'boxes_count' => 10,
                'pieces_per_packet' => 24,
                'boxes_per_carton' => 2,
                'carton_cbm' => 0.1,
                'unit_cost' => 2,
                'colors' => [],
                'groups' => [],
                ...$line,
            ]],
            ...$invoice,
        ];
    }

    /**
     * EN: Only the purchase invoices are listed, searched by name or supplier, filtered by status, with the next steps of each row.
     * AR: تنعرض بس فواتير الشراء، تنبحث بالاسم أو المورد، وتتفلتر بالحالة، ويا الخطوات الجاية لكل صف.
     */
    public function test_the_list_shows_only_purchase_invoices_with_their_filters(): void
    {
        $supplier = Supplier::factory()->create(['name_ar' => 'علي']);
        Invoice::factory()->purchase()->create(['supplier_id' => $supplier->id]);
        Invoice::factory()->purchase()->importerConfirmed()->create();
        Invoice::factory()->create();

        $this->actingAs($this->userWith('invoice.view', 'invoice.update'));

        $this->getJson('/admin-api/purchase-invoices')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/admin-api/purchase-invoices?search='.rawurlencode('علي'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.supplier.name', 'علي')
            ->assertJsonPath('data.0.stage.value', InvoiceStage::InProduction->value)
            ->assertJsonPath('data.0.can.advance_stage', true);
        $this->getJson('/admin-api/purchase-invoices?filter[status]=importer_confirmed')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.awaited_confirmation.value', 'receiver');
    }

    /**
     * EN: The list needs the view permission.
     * AR: القائمة تحتاج صلاحية العرض.
     */
    public function test_the_list_needs_the_permission(): void
    {
        $this->actingAs($this->userWith())->getJson('/admin-api/purchase-invoices')->assertForbidden();
    }

    /**
     * EN: Creating an invoice saves its line with its price, groups by power and colors, confirms it as the importer,
     *     turns the initial payment into its first payoff, and keeps the bank's rate for a user without the rate permission.
     * AR: إنشاء الفاتورة يحفظ بندها بسعره ومجموعاته حسب الدرجة وألوانه، ويأكدها كمستورد،
     *     ويخلي الدفعة الأولى أول تسديد إلها، ويبقي سعر البنك لليوزر اللي ما عنده صلاحية السعر.
     */
    public function test_create_saves_the_lines_groups_colors_and_initial_payment(): void
    {
        $this->bankAnswers(6.8);
        [$low, $high] = [LensPower::factory()->create(['value' => 1]), LensPower::factory()->create(['value' => 1.5])];
        [$red, $black] = [Color::factory()->create(['name' => 'Red']), Color::factory()->create(['name' => 'Black'])];
        $this->actingAs($user = $this->userWith('invoice.view', 'invoice.create'));

        $this->getJson('/admin-api/purchase-invoices/create')->assertOk()
            ->assertJsonPath('exchange_rate', 6.8)
            ->assertJsonPath('exchange_rate_source.value', 'agricultural_bank_of_china')
            ->assertJsonPath('can_change_exchange_rate', false);

        $id = $this->postJson('/admin-api/purchase-invoices', $this->payload([
            'groups' => [['lens_power_id' => $low->id, 'boxes_count' => 6], ['lens_power_id' => $high->id, 'boxes_count' => 4]],
            'colors' => [['color_id' => $red->id, 'pieces' => 12], ['color_id' => $black->id, 'pieces' => 12]],
        ], ['exchange_rate' => 9, 'initial_payment' => 100]))->assertCreated()->json('data.id');

        $invoice = Invoice::query()->findOrFail($id);
        $this->assertEqualsWithDelta(6.8, $invoice->exchange_rate, 0.0001);
        $this->assertSame(InvoiceStatus::ImporterConfirmed, $invoice->status);
        $this->assertSame($user->id, $invoice->importer_checker_id);
        $this->assertEqualsWithDelta(480, $invoice->amount, 0.001);
        $this->assertEqualsWithDelta(100, $invoice->paid_amount, 0.001);
        $this->assertTrue(Payoff::query()->sole()->is_initial_payment);
        $this->assertEquals([$low->id => 144, $high->id => 96], Batch::query()->pluck('quantity', 'lens_power_id')->all());
        $this->assertSame(2, InvoiceItem::query()->sole()->colors()->count());
    }

    /**
     * EN: A line typed in dozens is saved in packets: 20 dozens of a packet of 24 pieces are 10 packets.
     * AR: البند المكتوب بالدرزن ينحفظ بالباكيتات: 20 درزن من باكيت 24 قطعة هي 10 باكيتات.
     */
    public function test_a_line_typed_in_dozens_is_saved_in_packets(): void
    {
        $this->bankAnswers(7);
        $this->actingAs($this->userWith('invoice.view', 'invoice.create'));
        $this->getJson('/admin-api/purchase-invoices/create');

        $this->postJson('/admin-api/purchase-invoices', $this->payload(['entry_unit' => 'dozens', 'sets' => 20, 'boxes_count' => null]))->assertCreated();

        $this->assertEqualsWithDelta(10, InvoiceItem::query()->sole()->boxes_count, 0.0001);
    }

    /**
     * EN: The rules of the form are kept: groups that do not add up to the packets, colors that do not fill the packet,
     *     dozens that do not make whole packets, a dollar rate out of range, and an initial payment above the amount are refused.
     * AR: قواعد الفورم تنحفظ: المجموعات اللي ما يطلع مجموعها الباكيتات، والألوان اللي ما تملي الباكيت،
     *     والدرازن اللي ما تسوي باكيتات كاملة، وسعر الدولار برا الحدود، والدفعة الأولى الأكثر من المبلغ تنرفض.
     */
    public function test_the_form_rules_are_kept(): void
    {
        $this->bankAnswers(7);
        $power = LensPower::factory()->create(['value' => 1]);
        $color = Color::factory()->create();
        $this->actingAs($this->userWith('invoice.view', 'invoice.create'));
        $this->getJson('/admin-api/purchase-invoices/create');

        $this->postJson('/admin-api/purchase-invoices', $this->payload(['groups' => [['lens_power_id' => $power->id, 'boxes_count' => 8]]]))
            ->assertJsonValidationErrors('items.0.groups');
        $this->postJson('/admin-api/purchase-invoices', $this->payload(['colors' => [['color_id' => $color->id, 'pieces' => 20]]]))
            ->assertJsonValidationErrors('items.0.colors');
        $this->postJson('/admin-api/purchase-invoices', $this->payload(['entry_unit' => 'dozens', 'sets' => 21, 'pieces_per_packet' => 20]))
            ->assertJsonValidationErrors('items.0.sets');
        $this->postJson('/admin-api/purchase-invoices', $this->payload([], ['dinar_rate' => 1800]))
            ->assertJsonValidationErrors('dinar_rate');
        $this->postJson('/admin-api/purchase-invoices', $this->payload([], ['initial_payment' => 5000]))
            ->assertJsonValidationErrors('initial_payment');
        $this->postJson('/admin-api/purchase-invoices', $this->payload([], ['items' => []]))
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, Invoice::query()->count());
    }

    /**
     * EN: Editing an invoice changes its lines: a kept line is updated, a removed one is deleted, and the amounts follow.
     * AR: تعديل الفاتورة يغيّر بنودها: البند الباقي يتحدث، والمحذوف ينحذف، والمبالغ تتبعها.
     */
    public function test_update_saves_the_lines_and_recalculates(): void
    {
        $invoice = Invoice::factory()->purchase()->create(['exchange_rate' => 7, 'dinar_rate' => 170, 'invoice_category_id' => InvoiceCategory::factory()]);
        $kept = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => 10, 'pieces_per_packet' => 24, 'boxes_per_carton' => 1, 'carton_cbm' => 0.05, 'unit_cost' => 2, 'price' => 480]);
        $removed = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => 5, 'pieces_per_packet' => 24, 'boxes_per_carton' => 1, 'carton_cbm' => 0.05, 'unit_cost' => 1, 'price' => 120]);
        $this->actingAs($this->userWith('invoice.view', 'invoice.update'));

        $form = $this->getJson("/admin-api/purchase-invoices/{$invoice->id}")->assertOk()->json('data');
        $this->assertCount(2, $form['items']);

        $line = collect($form['items'])->firstWhere('id', $kept->id);
        $this->putJson("/admin-api/purchase-invoices/{$invoice->id}", [
            ...collect($form)->only(['supplier_id', 'invoice_category_id', 'date', 'exchange_rate', 'dinar_rate', 'cbm_price', 'notes'])->all(),
            'items' => [[...$line, 'boxes_count' => 20]],
        ])->assertOk();

        $this->assertSoftDeleted($removed);
        $this->assertEqualsWithDelta(960, $invoice->fresh()->amount, 0.001);
    }

    /**
     * EN: An invoice the receiver confirmed cannot be edited.
     * AR: الفاتورة اللي أكدها المستلم ما تتعدل.
     */
    public function test_a_confirmed_invoice_cannot_be_edited(): void
    {
        $invoice = Invoice::factory()->purchase()->confirmed()->create();
        $this->actingAs($this->userWith('invoice.view', 'invoice.update'));

        $this->putJson("/admin-api/purchase-invoices/{$invoice->id}", ['items' => []])->assertForbidden();
    }

    /**
     * EN: The stage moves one step forward, and the receiver confirms by writing the invoice amount again; a wrong amount is refused.
     * AR: المرحلة تتقدم خطوة وحدة، والمستلم يأكد بكتابة مبلغ الفاتورة مرة ثانية، والمبلغ الغلط ينرفض.
     */
    public function test_advance_stage_and_confirm_as_the_receiver(): void
    {
        $invoice = Invoice::factory()->purchase()->importerConfirmed()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => 10, 'pieces_per_packet' => 24, 'boxes_per_carton' => 1, 'carton_cbm' => 0.05, 'unit_cost' => 2, 'price' => 480]);
        $this->actingAs($this->userWith('invoice.view', 'invoice.update', 'invoice.confirm_as_receiver'));

        $this->postJson("/admin-api/purchase-invoices/{$invoice->id}/advance-stage")->assertOk();
        $this->assertSame(InvoiceStage::Manufactured, $invoice->fresh()->stage);

        $this->postJson("/admin-api/purchase-invoices/{$invoice->id}/confirm", ['amount' => 100])->assertJsonValidationErrors('amount');
        $this->postJson("/admin-api/purchase-invoices/{$invoice->id}/confirm", ['amount' => 480])->assertOk();
        $this->assertSame(InvoiceStatus::Confirmed, $invoice->fresh()->status);
    }

    /**
     * EN: Only a super user takes back the last confirmation.
     * AR: بس السوبر يوزر يرجّع آخر تأكيد.
     */
    public function test_only_a_super_user_reverts_a_confirmation(): void
    {
        $invoice = Invoice::factory()->purchase()->importerConfirmed()->create();

        $this->actingAs($this->userWith('invoice.view', 'invoice.update'))
            ->postJson("/admin-api/purchase-invoices/{$invoice->id}/revert-confirmation")->assertForbidden();

        $this->actingAs(User::factory()->create(['is_active' => true, 'is_super' => true]))
            ->postJson("/admin-api/purchase-invoices/{$invoice->id}/revert-confirmation")->assertOk();
        $this->assertSame(InvoiceStatus::Pending, $invoice->fresh()->status);
    }

    /**
     * EN: The page of an invoice carries its lines, its split facts, its container, and its print links.
     * AR: صفحة الفاتورة بيها بنودها، ومعلومات التقسيم، والكونتينر، وروابط الطباعة.
     */
    public function test_show_carries_the_lines_and_links(): void
    {
        $invoice = Invoice::factory()->purchase()->create(['container_id' => Container::factory()]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => 2, 'pieces_per_packet' => 12, 'boxes_per_carton' => 1, 'carton_cbm' => 0.05, 'unit_cost' => 1, 'price' => 24]);
        $this->actingAs($this->userWith('invoice.view'));

        $this->getJson("/admin-api/purchase-invoices/{$invoice->id}")->assertOk()
            ->assertJsonPath('data.items.0.quantity', 24)
            ->assertJsonPath('data.container.id', $invoice->container_id)
            ->assertJsonPath('data.cbm_price_locked', true)
            ->assertJsonPath('data.print_url', route('purchase-invoices.print', ['invoice' => $invoice]))
            ->assertJsonPath('data.label_url', route('purchase-invoices.label', $invoice));
    }

    /**
     * EN: A general invoice is not reached through the purchase invoices.
     * AR: الفاتورة العامة ما توصلها من فواتير الشراء.
     */
    public function test_a_general_invoice_is_not_found(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->userWith('invoice.view'))->getJson("/admin-api/purchase-invoices/{$invoice->id}")->assertNotFound();
    }

    /**
     * EN: The two waiting counts and the pie of the suppliers we buy from the most.
     * AR: عددين الانتظار والدائرة مال الموردين اللي نشتري منهم أكثر.
     */
    public function test_the_metrics_and_chart(): void
    {
        Invoice::factory()->purchase()->create();
        Invoice::factory()->purchase()->importerConfirmed()->create();
        $this->actingAs($this->userWith('invoice.view'));

        $this->getJson('/admin-api/purchase-invoices/metrics/pending')->assertOk()->assertJsonPath('value.raw', 1);
        $this->getJson('/admin-api/purchase-invoices/metrics/importer_confirmed')->assertOk()->assertJsonPath('value.raw', 1);
        $this->getJson('/admin-api/purchase-invoices/metrics/other')->assertNotFound();
        $this->getJson('/admin-api/purchase-invoices/chart')->assertOk()->assertJsonStructure(['heading', 'parts']);
    }

    /**
     * EN: An image is uploaded to the invoices folder and sent back as its path; a path outside that folder is refused on save.
     * AR: الصورة تنرفع لمجلد الفواتير وترجع بمسارها، والمسار البرا هذا المجلد ينرفض بالحفظ.
     */
    public function test_images_are_uploaded_then_saved_by_path(): void
    {
        Storage::fake('public');
        $this->bankAnswers(7);
        $this->actingAs($this->userWith('invoice.view', 'invoice.create'));
        $this->getJson('/admin-api/purchase-invoices/create');

        $path = $this->postJson('/admin-api/purchase-invoices/uploads', ['kind' => 'images', 'file' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()->json('path');
        $this->assertStringStartsWith('invoices/', $path);

        $this->postJson('/admin-api/purchase-invoices', $this->payload([], ['images' => ['../.env']]))->assertJsonValidationErrors('images.0');
        $id = $this->postJson('/admin-api/purchase-invoices', $this->payload([], ['images' => [$path]]))->assertCreated()->json('data.id');
        $this->assertSame([$path], Invoice::query()->findOrFail($id)->images);
    }

    /**
     * EN: The line's item is described as it is chosen: its packet, stock, and sale price; its cost only to whoever may see it.
     * AR: مادة البند تنوصف أول ما تنختار: باكيتها ومخزونها وسعر بيعها، وكلفتها بس للي يگدر يشوفها.
     */
    public function test_the_line_item_facts(): void
    {
        $item = Item::factory()->create(['pieces_per_packet' => 24, 'sale_price' => 50]);
        $this->actingAs($this->userWith('invoice.view', 'invoice.create'));

        $this->getJson("/admin-api/purchase-invoices/line-item/{$item->specificDescription->id}")->assertOk()
            ->assertJsonPath('pieces_per_packet', 24)
            ->assertJsonPath('sale_price', 50)
            ->assertJsonPath('cost_price', null);
    }

    /**
     * EN: The form says whether the user may add a color, an invoice category, or a lens power from inside it, on the new invoice and
     *     on the edit page, so the add buttons show only to who may add them; the server still refuses the others.
     * AR: الفورم يگول إذا اليوزر يگدر يضيف لون أو صنف فاتورة أو درجة من داخله، بالفاتورة الجديدة وبصفحة التعديل،
     *     حتى أزرار الإضافة تطلع بس للي يگدر يضيف؛ والسيرفر يبقى يرفض الباقين.
     */
    public function test_the_form_says_which_choices_may_be_added_inline(): void
    {
        $this->bankAnswers(7.1);
        $invoice = Invoice::factory()->purchase()->create();
        $this->actingAs($this->userWith('invoice.view', 'invoice.create', 'invoice.update', 'color.create'));

        $this->getJson('/admin-api/purchase-invoices/create')->assertOk()
            ->assertJsonPath('can_create_options.colors', true)
            ->assertJsonPath('can_create_options.invoice_categories', false)
            ->assertJsonPath('can_create_options.lens_powers', false);
        $this->getJson("/admin-api/purchase-invoices/{$invoice->id}")->assertOk()
            ->assertJsonPath('can.create_options.colors', true)
            ->assertJsonPath('can.create_options.invoice_categories', false)
            ->assertJsonPath('can.create_options.lens_powers', false);

        $this->postJson('/admin-api/invoice-categories', ['name' => 'Frames'])->assertForbidden();
        $this->postJson('/admin-api/lens-powers', ['value' => 1.25])->assertForbidden();

        $this->actingAs($this->userWith('invoice.view', 'invoice.create', 'invoice_category.create', 'lens_power.create'));
        $this->getJson('/admin-api/purchase-invoices/create')->assertOk()
            ->assertJsonPath('can_create_options.colors', false)
            ->assertJsonPath('can_create_options.invoice_categories', true)
            ->assertJsonPath('can_create_options.lens_powers', true);
        $this->postJson('/admin-api/lens-powers', ['value' => 1.25])->assertCreated();
    }

    /**
     * EN: The list and each row say whether the user may open the containers, so the container shows as a link only to who may.
     * AR: القائمة وكل صف يگولون إذا اليوزر يگدر يفتح الحاويات، حتى الحاوية تطلع رابط بس للي يگدر.
     */
    public function test_the_list_says_whether_containers_may_be_opened(): void
    {
        Invoice::factory()->purchase()->create(['container_id' => Container::factory()]);

        $this->actingAs($this->userWith('invoice.view'))->getJson('/admin-api/purchase-invoices')->assertOk()
            ->assertJsonPath('can.view_containers', false)
            ->assertJsonPath('data.0.container.can_view', false);
        $this->actingAs($this->userWith('invoice.view', 'container.view'))->getJson('/admin-api/purchase-invoices')->assertOk()
            ->assertJsonPath('can.view_containers', true)
            ->assertJsonPath('data.0.container.can_view', true);
    }

    /**
     * EN: The purchase invoices list and a supplier's invoices read the same number of queries for many rows as for a few,
     *     even for a user whose row abilities ask whether each invoice is a purchase invoice.
     * AR: قائمة فواتير الشراء وفواتير المورد تقرا نفس عدد الاستعلامات للصفوف الهواية مثل القليلة،
     *     حتى لليوزر اللي صلاحيات صفوفه تسأل إذا كل فاتورة فاتورة شراء.
     */
    public function test_the_lists_do_not_query_once_per_row(): void
    {
        $supplier = Supplier::factory()->create();
        $this->actingAs($this->userWith('invoice.view', 'invoice.update', 'invoice.delete', 'invoice.confirm_as_importer', 'invoice.confirm_as_receiver', 'supplier.view', 'container.view'));
        $counts = [];

        foreach ([2, 6] as $rows) {
            Invoice::factory()->purchase()->count($rows - ($counts === [] ? 0 : 2))->create(['supplier_id' => $supplier->id, 'container_id' => Container::factory()]);

            foreach (['/admin-api/purchase-invoices', "/admin-api/suppliers/{$supplier->id}/invoices"] as $endpoint) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $this->getJson($endpoint)->assertOk()->assertJsonCount($rows, 'data');
                $counts[$endpoint][] = count(DB::getQueryLog());
                DB::disableQueryLog();
            }
        }

        foreach ($counts as $endpoint => [$few, $many]) {
            $this->assertSame($few, $many, $endpoint);
        }
    }
}
