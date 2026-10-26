<?php

namespace Tests\Feature;

use App\Enums\AppPermission;
use App\Enums\InvoiceStage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiPurchaseInvoicesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The application gets the purchase invoices, each with its label number, name, supplier, date, and stage labelled in the request's language.
     * AR: التطبيق ياخذ فواتير الشراء، كل وحدة برقم ملصقها واسمها ومورّدها وتاريخها ومرحلتها، والمرحلة مكتوبة بلغة الطلب.
     */
    public function test_application_gets_the_purchase_invoices(): void
    {
        Sanctum::actingAs($this->receiver());
        $supplier = Supplier::factory()->create(['name_ar' => 'علي']);
        $invoice = Invoice::factory()->purchase()->create(['supplier_id' => $supplier->id, 'date' => '2026-09-20']);

        $this->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/purchase-invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [[
                'id' => $invoice->id,
                'number' => "PI-{$invoice->id}",
                'name' => 'علي 1',
                'supplier' => 'علي',
                'date' => '2026-09-20',
                'stage' => ['value' => 'in_production', 'label' => 'قيد التصنيع'],
            ]]]);
    }

    /**
     * EN: A general invoice and a deleted purchase invoice do not appear in the list; only the purchase invoices that are not deleted do.
     * AR: الفاتورة العامة وفاتورة الشراء المحذوفة ما يطلعن بالقائمة، بس فواتير الشراء اللي مو محذوفة.
     */
    public function test_general_and_deleted_invoices_are_not_listed(): void
    {
        Sanctum::actingAs($this->receiver());
        $listed = Invoice::factory()->purchase()->create();
        Invoice::factory()->create();
        Invoice::factory()->purchase()->create()->delete();

        $this->getJson('/api/v1/purchase-invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $listed->id);
    }

    /**
     * EN: When the application asks for no order, the invoices come from the newest date to the oldest.
     * AR: إذا التطبيق ما طلب ترتيب، الفواتير تجي من الأحدث تاريخ للأقدم.
     */
    public function test_newest_invoices_come_first_by_default(): void
    {
        Sanctum::actingAs($this->receiver());
        $oldest = Invoice::factory()->purchase()->create(['date' => '2026-09-01']);
        $newest = Invoice::factory()->purchase()->create(['date' => '2026-09-10']);
        $middle = Invoice::factory()->purchase()->create(['date' => '2026-09-05']);

        $response = $this->getJson('/api/v1/purchase-invoices')->assertOk();

        $this->assertSame([$newest->id, $middle->id, $oldest->id], $response->json('data.*.id'));
    }

    /**
     * EN: With direction=asc, the invoices come from the oldest date to the newest.
     * AR: ويا direction=asc، الفواتير تجي من الأقدم تاريخ للأحدث.
     */
    public function test_oldest_invoices_come_first_when_asked(): void
    {
        Sanctum::actingAs($this->receiver());
        $oldest = Invoice::factory()->purchase()->create(['date' => '2026-09-01']);
        $newest = Invoice::factory()->purchase()->create(['date' => '2026-09-10']);
        $middle = Invoice::factory()->purchase()->create(['date' => '2026-09-05']);

        $response = $this->getJson('/api/v1/purchase-invoices?direction=asc')->assertOk();

        $this->assertSame([$oldest->id, $middle->id, $newest->id], $response->json('data.*.id'));
    }

    /**
     * EN: With stage=received, only the invoices at "Received" come back; the ones at other stages do not.
     * AR: ويا stage=received، بس الفواتير اللي مرحلتها "تم الاستلام" ترجع، واللي بمراحل ثانية لا.
     */
    public function test_invoices_are_filtered_by_their_stage(): void
    {
        Sanctum::actingAs($this->receiver());
        $received = Invoice::factory()->purchase()->create();
        $received->receive();
        Invoice::factory()->purchase()->create();
        Invoice::factory()->purchase()->create()->forceFill(['stage' => InvoiceStage::OnTheWay])->save();

        $this->getJson('/api/v1/purchase-invoices?stage=received')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $received->id)
            ->assertJsonPath('data.0.stage.value', 'received');
    }

    /**
     * EN: A stage that does not exist comes back as 422 on the stage field.
     * AR: مرحلة مو موجودة ترجع 422 على حقل المرحلة.
     */
    public function test_unknown_stage_is_rejected(): void
    {
        Sanctum::actingAs($this->receiver());

        $this->getJson('/api/v1/purchase-invoices?stage=lost')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('stage');
    }

    /**
     * EN: A user without the receiving permission cannot get the purchase invoices: 403.
     * AR: المستخدم اللي ما عنده صلاحية استلام البضاعة ما ياخذ فواتير الشراء: 403.
     */
    public function test_list_needs_the_receiving_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/purchase-invoices')->assertForbidden();
    }

    /**
     * EN: Opening one purchase invoice gives its label number, supplier, date, and stage, its total cartons, pieces, and CBM,
     *     and each of its lines with the item's name and image, the packets, the pieces in one packet, the pieces, the packets in one carton, the cartons, and the CBM.
     * AR: فتح فاتورة شراء وحدة يعطي رقم ملصقها ومورّدها وتاريخها ومرحلتها، ومجموع كراتينها وقطعها والـ CBM مالها،
     *     وكل سطر بيها باسم المادة وصورتها، وعدد الباكيتات، والقطع بالباكيت الواحد، والقطع، والباكيتات بالكارتونة الوحدة، والكراتين، والـ CBM.
     */
    public function test_application_opens_one_purchase_invoice_with_its_lines(): void
    {
        Sanctum::actingAs($this->receiver());
        $supplier = Supplier::factory()->create(['name_ar' => 'علي']);
        $invoice = Invoice::factory()->purchase()->create(['supplier_id' => $supplier->id, 'date' => '2026-09-20']);
        $frames = $this->line($invoice, 'Classic Round Glasses', packets: 10, piecesPerPacket: 12, packetsPerCarton: 4, cartonCbm: 0.25);
        $lenses = $this->line($invoice, 'Blue Light Lenses', packets: 5, piecesPerPacket: 6, packetsPerCarton: 2, cartonCbm: 0.5);

        $this->withHeader('Accept-Language', 'ar')
            ->getJson("/api/v1/purchase-invoices/{$invoice->id}")
            ->assertOk()
            ->assertJson(['data' => [
                'id' => $invoice->id,
                'number' => "PI-{$invoice->id}",
                'name' => 'علي 1',
                'supplier' => 'علي',
                'date' => '2026-09-20',
                'stage' => ['value' => 'in_production', 'label' => 'قيد التصنيع'],
                'cartons_count' => 6,
                'quantity' => 150,
                'cbm' => 2.25,
                'items' => [
                    [
                        'id' => $frames->id,
                        'item_id' => $frames->item_id,
                        'name' => 'Classic Round Glasses',
                        'boxes_count' => 10,
                        'pieces_per_packet' => 12,
                        'quantity' => 120,
                        'boxes_per_carton' => 4,
                        'cartons_count' => 3,
                        'cbm' => 0.75,
                    ],
                    [
                        'id' => $lenses->id,
                        'item_id' => $lenses->item_id,
                        'name' => 'Blue Light Lenses',
                        'boxes_count' => 5,
                        'pieces_per_packet' => 6,
                        'quantity' => 30,
                        'boxes_per_carton' => 2,
                        'cartons_count' => 3,
                        'cbm' => 1.5,
                    ],
                ],
            ]])
            ->assertJsonPath('data.items.0.image', fn (?string $image): bool => str_contains((string) $image, $frames->item->image));
    }

    /**
     * EN: An opened purchase invoice carries no money: no amount, no paid amount or remaining, and no price or cost on its lines.
     * AR: فاتورة الشراء المفتوحة ما بيها أي فلوس: لا مبلغ، ولا مدفوع ولا باقي، ولا سعر أو كلفة على سطورها.
     */
    public function test_opened_purchase_invoice_carries_no_money(): void
    {
        Sanctum::actingAs($this->receiver());
        $invoice = Invoice::factory()->purchase()->create(['exchange_rate' => 7.1, 'dinar_rate' => 170]);
        $this->line($invoice, 'Classic Round Glasses', packets: 10, piecesPerPacket: 12, packetsPerCarton: 4, cartonCbm: 0.25);

        $response = $this->getJson("/api/v1/purchase-invoices/{$invoice->id}")->assertOk();

        foreach (['amount', 'amount_usd', 'amount_iqd', 'paid_amount', 'remaining', 'transport_cost_usd', 'transport_cost_iqd'] as $field) {
            $response->assertJsonMissingPath("data.{$field}");
        }

        foreach (['price', 'unit_cost', 'unit_cost_usd', 'price_usd', 'unit_cost_iqd', 'price_iqd', 'final_unit_cost_iqd'] as $field) {
            $response->assertJsonMissingPath("data.items.0.{$field}");
        }
    }

    /**
     * EN: Opening a general invoice, a deleted purchase invoice, or an invoice that does not exist comes back as 404.
     * AR: فتح فاتورة عامة، أو فاتورة شراء محذوفة، أو فاتورة مو موجودة يرجع 404.
     */
    public function test_general_deleted_and_unknown_invoices_are_not_found(): void
    {
        Sanctum::actingAs($this->receiver());
        $general = Invoice::factory()->create();
        $deleted = Invoice::factory()->purchase()->create();
        $deleted->delete();

        $this->getJson("/api/v1/purchase-invoices/{$general->id}")->assertNotFound();
        $this->getJson("/api/v1/purchase-invoices/{$deleted->id}")->assertNotFound();
        $this->getJson('/api/v1/purchase-invoices/999999')->assertNotFound();
    }

    /**
     * EN: A user without the receiving permission cannot open a purchase invoice: 403.
     * AR: المستخدم اللي ما عنده صلاحية استلام البضاعة ما يگدر يفتح فاتورة شراء: 403.
     */
    public function test_opening_needs_the_receiving_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $invoice = Invoice::factory()->purchase()->create();

        $this->getJson("/api/v1/purchase-invoices/{$invoice->id}")->assertForbidden();
    }

    /**
     * EN: Adds a line of a new item with the given name to the purchase invoice, in the given packets and cartons.
     * AR: يضيف للفاتورة سطر لمادة جديدة بالاسم المعطى، بالباكيتات والكراتين المعطاة.
     */
    private function line(Invoice $invoice, string $name, int $packets, int $piecesPerPacket, int $packetsPerCarton, float $cartonCbm): InvoiceItem
    {
        $item = Item::factory()->create(['name' => $name]);

        return InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => $packets,
            'pieces_per_packet' => $piecesPerPacket,
            'boxes_per_carton' => $packetsPerCarton,
            'carton_cbm' => $cartonCbm,
            'unit_cost' => 15,
            'price' => $packets * $piecesPerPacket * 15,
        ]);
    }

    /**
     * EN: Creates a user who has only the receiving permission of the application.
     * AR: ينشئ مستخدم عنده بس صلاحية استلام البضاعة بالتطبيق.
     */
    private function receiver(): User
    {
        $user = User::factory()->create();

        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'permission' => AppPermission::Receiving->value,
        ]);

        return $user;
    }
}
