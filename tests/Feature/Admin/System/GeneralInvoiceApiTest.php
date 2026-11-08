<?php

namespace Tests\Feature\Admin\System;

use App\Enums\InvoiceType;
use App\Enums\OrderPaymentType;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SpecificDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneralInvoiceApiTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    private function generalInvoice(GeneralDescription $description, float $price = 100, array $attributes = []): Invoice
    {
        $invoice = Invoice::factory()->create(['general_description_id' => $description->id, 'type' => $description->type, ...$attributes]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $description->id])->id,
            'price' => $price,
        ]);

        return $invoice->refresh();
    }

    /**
     * EN: The list shows only the general invoices, never the purchase invoices, and marks a money transfer as read only.
     * AR: القائمة تطلع بس الفواتير العامة، مو فواتير الشراء، والتحويل المالي ينعلّم إنه للقراءة بس.
     */
    public function test_the_list_shows_general_invoices_and_marks_money_transfers(): void
    {
        $rent = GeneralDescription::factory()->create(['name' => 'Rent', 'type' => InvoiceType::Output]);
        $transfers = GeneralDescription::factory()->create(['name' => 'Transfers', 'type' => InvoiceType::Output]);
        $this->generalInvoice($rent, 250);
        $transfer = Invoice::factory()->create(['general_description_id' => $transfers->id, 'type' => InvoiceType::Output]);
        InvoiceItem::factory()->create([
            'invoice_id' => $transfer->id,
            'specific_description_id' => SpecificDescription::factory()->create(['general_description_id' => $transfers->id, 'transfer_type' => OrderPaymentType::Cash])->id,
            'price' => 40,
        ]);
        Invoice::factory()->purchase()->create();

        $response = $this->actingAs($this->superUser())->getJson('/admin-api/general-invoices?sort=amount&direction=desc')->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame([250, 40], array_map(fn ($amount) => (int) $amount, array_column($response->json('data'), 'amount')));
        $this->assertSame('Rent', $response->json('data.0.general_description.name'));
        $this->assertFalse($response->json('data.0.is_money_transfer'));
        $this->assertTrue($response->json('data.1.is_money_transfer'));
        $this->assertFalse($response->json('data.1.can.update'));
        $this->assertTrue($response->json('data.0.can.update'));
    }

    /**
     * EN: The list is filtered by type and by general description, and needs the view permission.
     * AR: القائمة تتفلتر بالنوع والوصف العام، وتحتاج صلاحية العرض.
     */
    public function test_the_list_is_filtered_and_needs_the_permission(): void
    {
        $rent = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $income = GeneralDescription::factory()->create(['type' => InvoiceType::Input]);
        $this->generalInvoice($rent);
        $this->generalInvoice($income);

        $this->actingAs($this->userWith())->getJson('/admin-api/general-invoices')->assertForbidden();

        $this->actingAs($this->userWith('invoice.view'))
            ->getJson('/admin-api/general-invoices?filter[type]=input')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type.value', 'input');

        $this->getJson("/admin-api/general-invoices?filter[general_description_id]={$rent->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.general_description.id', $rent->id);
    }

    /**
     * EN: A new general invoice is saved with its lines; its amount is the sum of the lines and its creator is the signed-in user.
     * AR: الفاتورة العامة الجديدة تنحفظ ويا سطورها، ومبلغها مجموع السطور، واللي سواها هو اليوزر الحالي.
     */
    public function test_create_saves_the_lines_and_the_creator(): void
    {
        $rent = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $office = SpecificDescription::factory()->create(['general_description_id' => $rent->id]);
        $store = SpecificDescription::factory()->create(['general_description_id' => $rent->id]);
        $user = $this->userWith('invoice.view', 'invoice.create');

        $id = $this->actingAs($user)->postJson('/admin-api/general-invoices', [
            'name' => 'October rent',
            'date' => '2026-10-01',
            'type' => 'output',
            'general_description_id' => $rent->id,
            'notes' => 'Paid in cash',
            'items' => [
                ['specific_description_id' => $office->id, 'price' => '1,500'],
                ['specific_description_id' => $store->id, 'price' => 500],
            ],
        ])->assertCreated()->json('data.id');

        $invoice = Invoice::query()->findOrFail($id);
        $this->assertSame(2000.0, $invoice->amount);
        $this->assertSame($user->id, $invoice->user_id);
        $this->assertSame([], $invoice->images);
        $this->assertCount(2, $invoice->items);
    }

    /**
     * EN: A line must be under the invoice's general description, an invoice needs a line, and the type must match the general description.
     * AR: السطر لازم يكون تحت الوصف العام للفاتورة، والفاتورة تحتاج سطر، والنوع لازم يطابق الوصف العام.
     */
    public function test_create_is_validated(): void
    {
        $rent = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $other = SpecificDescription::factory()->create();
        $this->actingAs($this->userWith('invoice.create'));

        $this->postJson('/admin-api/general-invoices', [
            'name' => 'Rent', 'date' => '2026-10-01', 'type' => 'input', 'general_description_id' => $rent->id,
            'items' => [['specific_description_id' => $other->id, 'price' => 10]],
        ])->assertJsonValidationErrors(['general_description_id', 'items.0.specific_description_id']);

        $this->postJson('/admin-api/general-invoices', [
            'name' => 'Rent', 'date' => '2026-10-01', 'type' => 'output', 'general_description_id' => $rent->id, 'items' => [],
        ])->assertJsonValidationErrors('items');

        $this->postJson('/admin-api/general-invoices', [
            'name' => 'Rent', 'date' => '2026-10-01', 'type' => 'output', 'general_description_id' => GeneralDescription::purchases()->id,
            'items' => [['specific_description_id' => $other->id, 'price' => 10]],
        ])->assertJsonValidationErrors('general_description_id');
    }

    /**
     * EN: A general description that holds transfer descriptions cannot be saved on an invoice: money is never withdrawn by hand.
     * AR: الوصف العام اللي بيه أوصاف تحويل ما ينحفظ على فاتورة: الفلوس ما تنسحب باليد.
     */
    public function test_a_transfer_general_description_cannot_be_saved(): void
    {
        $transfers = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $cash = SpecificDescription::factory()->create(['general_description_id' => $transfers->id, 'transfer_type' => OrderPaymentType::Cash]);

        $this->actingAs($this->superUser())->postJson('/admin-api/general-invoices', [
            'name' => 'Withdraw', 'date' => '2026-10-01', 'type' => 'output', 'general_description_id' => $transfers->id,
            'items' => [['specific_description_id' => $cash->id, 'price' => 10]],
        ])->assertJsonValidationErrors(['general_description_id' => __('filament/resources/general_invoice.money_transfer_notice.cannot_save')]);
    }

    /**
     * EN: Only a super user edits a general invoice; the lines are added, changed and removed, and the amount follows them.
     * AR: بس السوبر يعدّل الفاتورة العامة؛ السطور تنضاف وتتغير وتنشال، والمبلغ يمشي وياها.
     */
    public function test_update_syncs_the_lines_and_needs_a_super_user(): void
    {
        $rent = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $invoice = $this->generalInvoice($rent, 100);
        $kept = $invoice->items()->first();
        $added = SpecificDescription::factory()->create(['general_description_id' => $rent->id]);
        $body = [
            'name' => 'Changed', 'date' => '2026-10-02', 'type' => 'output', 'general_description_id' => $rent->id,
            'items' => [
                ['id' => $kept->id, 'specific_description_id' => $kept->specific_description_id, 'price' => 300],
                ['specific_description_id' => $added->id, 'price' => 50],
            ],
        ];

        $this->actingAs($this->userWith('invoice.view', 'invoice.update'))->putJson("/admin-api/general-invoices/{$invoice->id}", $body)->assertForbidden();

        $this->actingAs($this->superUser())->putJson("/admin-api/general-invoices/{$invoice->id}", $body)
            ->assertOk()
            ->assertJsonPath('data.name', 'Changed');

        $this->assertSame(350.0, $invoice->refresh()->amount);
        $this->assertSame(2, $invoice->items()->count());

        $body['items'] = [['id' => $kept->id, 'specific_description_id' => $kept->specific_description_id, 'price' => 300]];
        $this->putJson("/admin-api/general-invoices/{$invoice->id}", $body)->assertOk();
        $this->assertSame(300.0, $invoice->refresh()->amount);
    }

    /**
     * EN: A money transfer invoice is read only, even for a super user.
     * AR: فاتورة التحويل المالي للقراءة بس، حتى للسوبر.
     */
    public function test_a_money_transfer_cannot_be_updated(): void
    {
        $transfers = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $invoice = Invoice::factory()->create(['general_description_id' => $transfers->id, 'type' => InvoiceType::Output]);
        $cash = SpecificDescription::factory()->create(['general_description_id' => $transfers->id, 'transfer_type' => OrderPaymentType::Cash]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'specific_description_id' => $cash->id, 'price' => 10]);

        $this->actingAs($this->superUser())
            ->getJson("/admin-api/general-invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.is_money_transfer', true)
            ->assertJsonPath('data.can.update', false);

        $this->putJson("/admin-api/general-invoices/{$invoice->id}", [
            'name' => 'x', 'date' => '2026-10-02', 'type' => 'output', 'general_description_id' => $transfers->id,
            'items' => [['specific_description_id' => $cash->id, 'price' => 99]],
        ])->assertForbidden();
    }

    /**
     * EN: The page of an invoice shows its lines; a purchase invoice is not found here.
     * AR: صفحة الفاتورة تطلع سطورها؛ وفاتورة الشراء ما تنلگه هنا.
     */
    public function test_show_has_the_lines_and_hides_purchase_invoices(): void
    {
        $rent = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $invoice = $this->generalInvoice($rent, 75);
        $purchase = Invoice::factory()->purchase()->create();
        $this->actingAs($this->userWith('invoice.view'));

        $this->getJson("/admin-api/general-invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.price', 75)
            ->assertJsonPath('data.can.update', false);

        $this->getJson("/admin-api/general-invoices/{$purchase->id}")->assertNotFound();
    }

    /**
     * EN: A general invoice is deleted within a day of creating it, and only a super user restores it.
     * AR: الفاتورة العامة تنحذف خلال يوم من إنشائها، وبس السوبر يرجعها.
     */
    public function test_delete_within_a_day_and_restore_by_a_super_user(): void
    {
        $rent = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $fresh = $this->generalInvoice($rent);
        $old = $this->generalInvoice($rent);
        $old->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        $this->actingAs($this->userWith('invoice.view', 'invoice.delete', 'invoice.restore'));

        $this->deleteJson("/admin-api/general-invoices/{$old->id}")->assertForbidden();
        $this->deleteJson("/admin-api/general-invoices/{$fresh->id}")->assertNoContent();
        $this->assertSoftDeleted($fresh);

        $this->getJson('/admin-api/general-invoices?trashed=only')->assertJsonPath('data.0.can.restore', false);
        $this->postJson("/admin-api/general-invoices/{$fresh->id}/restore")->assertForbidden();

        $this->actingAs($this->superUser())->postJson("/admin-api/general-invoices/{$fresh->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($fresh);
    }

    /**
     * EN: An image is uploaded to the public disk and its path is saved on the invoice; a path outside the invoices folder is refused.
     * AR: الصورة تنرفع للديسك العام ومسارها ينحفظ ويا الفاتورة؛ والمسار اللي برا مجلد الفواتير ينرفض.
     */
    public function test_images_are_uploaded_and_saved(): void
    {
        Storage::fake('public');
        $rent = GeneralDescription::factory()->create(['type' => InvoiceType::Output]);
        $line = SpecificDescription::factory()->create(['general_description_id' => $rent->id]);
        $this->actingAs($this->userWith('invoice.create'));

        $path = $this->postJson('/admin-api/general-invoices/images', ['image' => UploadedFile::fake()->image('receipt.jpg')])
            ->assertOk()
            ->json('path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('invoices/', $path);

        $this->postJson('/admin-api/general-invoices/images', ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertJsonValidationErrors('image');

        $body = ['name' => 'Rent', 'date' => '2026-10-01', 'type' => 'output', 'general_description_id' => $rent->id, 'items' => [['specific_description_id' => $line->id, 'price' => 5]]];

        $this->postJson('/admin-api/general-invoices', [...$body, 'images' => ['../.env']])->assertJsonValidationErrors('images.0');

        $id = $this->postJson('/admin-api/general-invoices', [...$body, 'images' => [$path]])->assertCreated()->json('data.id');
        $this->assertSame([$path], Invoice::query()->findOrFail($id)->images);
    }

    /**
     * EN: The expenses chart gives the general expenses by description, the largest first.
     * AR: مخطط المصاريف يطلع المصاريف العامة حسب الوصف، الأكبر أول.
     */
    public function test_the_expenses_chart(): void
    {
        $rent = GeneralDescription::factory()->create(['name' => 'Rent', 'type' => InvoiceType::Output]);
        $salaries = GeneralDescription::factory()->create(['name' => 'Salaries', 'type' => InvoiceType::Output]);
        $this->generalInvoice($rent, 100);
        $this->generalInvoice($salaries, 400);

        $this->actingAs($this->userWith('invoice.view'))
            ->getJson('/admin-api/general-invoices/expenses-chart')
            ->assertOk()
            ->assertJsonPath('slices.0.name', 'Salaries')
            ->assertJsonPath('slices.0.value', 400)
            ->assertJsonPath('slices.1.name', 'Rent');
    }

    /**
     * EN: The general description choices of the form are of the chosen type, never the purchases one, and say which hold transfers.
     * AR: خيارات الوصف العام بالفورم من النوع المختار، مو وصف المشتريات، وتگول أيها بيه تحويلات.
     */
    public function test_the_form_choices(): void
    {
        $rent = GeneralDescription::factory()->create(['name' => 'Rent', 'type' => InvoiceType::Output]);
        $transfers = GeneralDescription::factory()->create(['name' => 'Transfers', 'type' => InvoiceType::Output]);
        SpecificDescription::factory()->create(['general_description_id' => $transfers->id, 'transfer_type' => OrderPaymentType::Cash]);
        GeneralDescription::factory()->create(['name' => 'Income', 'type' => InvoiceType::Input]);
        $office = SpecificDescription::factory()->create(['general_description_id' => $rent->id, 'name' => 'Office']);
        $this->actingAs($this->userWith('invoice.create'));

        $choices = $this->getJson('/admin-api/system-options/general-descriptions?type=output')->assertOk()->json('data');
        $this->assertSame(['Rent', 'Transfers'], array_column($choices, 'label'));
        $this->assertSame([false, true], array_column($choices, 'holds_transfer'));

        $this->getJson("/admin-api/system-options/specific-descriptions?general_description_id={$rent->id}")
            ->assertOk()
            ->assertJsonPath('data.0.value', $office->id)
            ->assertJsonCount(1, 'data');
    }
}
