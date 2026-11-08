<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\Batch;
use App\Models\Color;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\LensPower;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BatchApiTest extends TestCase
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

    /**
     * EN: Buys the item on an invoice of the given state: one line of 2 packets of 12 pieces at the given power, with 12 red pieces in each packet.
     * AR: يشتري المادة بفاتورة بالحالة المعطاة: بند واحد بيه 2 باكيت كل باكيت 12 قطعة بالدرجة المعطاة، وبكل باكيت 12 قطعة حمرة.
     */
    private function buy(Item $item, Invoice $invoice, ?LensPower $power = null): void
    {
        $line = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'specific_description_id' => $item->specificDescription->id,
            'boxes_count' => 2,
            'pieces_per_packet' => 12,
            'price' => 1000,
        ]);
        $line->colors()->create(['color_id' => Color::query()->firstOrCreate(['name' => 'Red'])->id, 'pieces' => 12]);

        if ($power !== null) {
            $line->groups()->create(['lens_power_id' => $power->id, 'boxes_count' => 2]);
        }
    }

    /**
     * EN: The batches list needs the batch permission, and shows each batch with its item, power, colors, invoice, and status.
     * AR: قائمة الدفعات تحتاج صلاحية الدفعات، وتعرض كل دفعة ويا مادتها ودرجتها وألوانها وفاتورتها وحالتها.
     */
    public function test_the_list_shows_the_batches(): void
    {
        $item = Item::factory()->create(['name' => 'Frame']);
        $power = LensPower::factory()->create(['value' => 1.25]);
        $invoice = Invoice::factory()->purchase()->confirmed()->create();
        $this->buy($item, $invoice, $power);

        $this->actingAs($this->userWith('item.view'))->getJson('/admin-api/batches')->assertForbidden();

        $this->actingAs($this->userWith('batch.view'))->getJson('/admin-api/batches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item.name', 'Frame')
            ->assertJsonPath('data.0.lens_power', '1.25')
            ->assertJsonPath('data.0.colors.0.color', 'Red')
            ->assertJsonPath('data.0.colors.0.pieces', 24)
            ->assertJsonPath('data.0.invoice_id', $invoice->id)
            ->assertJsonPath('data.0.status.value', 'confirmed')
            ->assertJsonPath('data.0.invoice_path', null);
    }

    /**
     * EN: The invoice of a batch is a link only for a user who can open the invoice.
     * AR: فاتورة الدفعة تصير رابط بس لليوزر اللي يگدر يفتح الفاتورة.
     */
    public function test_the_invoice_link_follows_the_invoice_permission(): void
    {
        $invoice = Invoice::factory()->purchase()->confirmed()->create();
        $this->buy(Item::factory()->create(), $invoice);

        $this->actingAs($this->userWith('batch.view', 'invoice.view'))->getJson('/admin-api/batches')
            ->assertJsonPath('data.0.invoice_path', "/purchase-invoices/{$invoice->id}");
    }

    /**
     * EN: The list is filtered by item, by invoice status, and by power, and searched by the item's name.
     * AR: القائمة تتفلتر حسب المادة وحالة الفاتورة والدرجة، وتنبحث باسم المادة.
     */
    public function test_the_list_is_filtered(): void
    {
        $frame = Item::factory()->create(['name' => 'Frame']);
        $lens = Item::factory()->create(['name' => 'Lens']);
        $power = LensPower::factory()->create(['value' => 2]);
        $this->buy($frame, Invoice::factory()->purchase()->confirmed()->create(), $power);
        $this->buy($frame, Invoice::factory()->purchase()->importerConfirmed()->create());
        $this->buy($lens, Invoice::factory()->purchase()->confirmed()->create());
        $this->actingAs($this->userWith('batch.view'));

        $this->assertSame(3, Batch::query()->count());
        $this->getJson("/admin-api/batches?filter[item_id]={$frame->id}")->assertJsonCount(2, 'data');
        $this->getJson('/admin-api/batches?filter[status]=importer_confirmed')->assertJsonCount(1, 'data');
        $this->getJson("/admin-api/batches?filter[lens_power_id]={$power->id}")->assertJsonCount(1, 'data');
        $this->getJson('/admin-api/batches?search=lens')->assertJsonCount(1, 'data')->assertJsonPath('data.0.item.name', 'Lens');
    }
}
