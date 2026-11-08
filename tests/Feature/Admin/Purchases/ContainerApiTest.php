<?php

namespace Tests\Feature\Admin\Purchases;

use App\Models\Container;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContainerApiTest extends TestCase
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

    private function invoiceWithCartons(int $cartons): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create(['cbm_price' => 100]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => $cartons, 'pieces_per_packet' => 12, 'boxes_per_carton' => 1, 'carton_cbm' => 0.5, 'unit_cost' => 1, 'price' => $cartons * 12]);

        return $invoice->fresh();
    }

    /**
     * EN: The containers list carries each container's CBM and transport from its invoices.
     * AR: قائمة الكونتينرات بيها حجم ونقل كل كونتينر من فواتيره.
     */
    public function test_the_list_sums_the_invoices(): void
    {
        $container = Container::factory()->create();
        $invoice = $this->invoiceWithCartons(4);
        $invoice->container()->associate($container)->save();

        $this->actingAs($this->userWith('container.view'))
            ->getJson('/admin-api/containers')->assertOk()
            ->assertJsonPath('data.0.total_cbm', 2)
            ->assertJsonPath('data.0.transport_cost', 200);
    }

    /**
     * EN: The international number is written in capitals without spaces, must look like four letters and seven digits, and is unique.
     * AR: الرقم الدولي ينكتب بالحروف الكبيرة بلا فراغات، ولازم يكون أربع حروف وسبع أرقام، وما يتكرر.
     */
    public function test_create_normalizes_and_checks_the_international_number(): void
    {
        $this->actingAs($this->userWith('container.view', 'container.create'));

        $this->postJson('/admin-api/containers', ['international_number' => 'mscu 123456 7', 'cbm_price' => 100, 'loading_date' => '2026-11-01', 'expected_arrival_date' => '2026-12-31'])
            ->assertCreated()->assertJsonPath('data.international_number', 'MSCU1234567');
        $this->postJson('/admin-api/containers', ['international_number' => 'MSCU1234567', 'cbm_price' => 100])->assertJsonValidationErrors('international_number');
        $this->postJson('/admin-api/containers', ['international_number' => 'AB12', 'cbm_price' => 100])->assertJsonValidationErrors('international_number');
        $this->postJson('/admin-api/containers', ['cbm_price' => 100, 'loading_date' => '2026-11-01', 'expected_arrival_date' => '2026-10-01'])->assertJsonValidationErrors('expected_arrival_date');
    }

    /**
     * EN: Invoices are added to a container and taken out of it, and the container's cards follow.
     * AR: الفواتير تنضاف للكونتينر وتطلع منه، وكروت الكونتينر تتبعها.
     */
    public function test_invoices_are_added_and_taken_out(): void
    {
        $container = Container::factory()->create();
        $invoice = $this->invoiceWithCartons(4);
        $this->actingAs($this->userWith('container.view', 'container.update', 'invoice.view'));

        $this->getJson("/admin-api/containers/{$container->id}/invoice-options")->assertOk()->assertJsonPath('data.0.value', $invoice->id);
        $this->postJson("/admin-api/containers/{$container->id}/invoices", ['ids' => [$invoice->id]])->assertOk();
        $this->assertSame($container->id, $invoice->fresh()->container_id);
        $this->assertNotNull($invoice->fresh()->cbm_price_locked_at);
        $this->getJson("/admin-api/containers/{$container->id}/metrics/cbm")->assertOk()->assertJsonPath('value.raw', 2);
        $this->getJson("/admin-api/containers/{$container->id}/invoices")->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/admin-api/containers/{$container->id}/invoices/take-out", ['ids' => [$invoice->id]])->assertOk();
        $this->assertNull($invoice->fresh()->container_id);
    }

    /**
     * EN: Part of an invoice is shipped: the chosen cartons become a new invoice in the container, and the original keeps the rest.
     * AR: جزء من الفاتورة ينشحن: الكراتين المختارة تصير فاتورة جديدة بالكونتينر، والأصلية تبقى بيها الباقي.
     */
    public function test_part_of_an_invoice_is_split_into_the_container(): void
    {
        $container = Container::factory()->create();
        $invoice = $this->invoiceWithCartons(4);
        $line = $invoice->items()->sole();
        $this->actingAs($this->userWith('container.view', 'container.update', 'invoice.view'));

        $this->getJson("/admin-api/containers/{$container->id}/splittable")->assertOk()->assertJsonPath('data.0.value', $invoice->id);
        $this->getJson("/admin-api/containers/{$container->id}/splittable/{$invoice->id}")->assertOk()->assertJsonPath('lines.0.cartons_count', 4);
        $this->postJson("/admin-api/containers/{$container->id}/split", ['invoice_id' => $invoice->id, 'cartons' => [$line->id => 1]])->assertOk();

        $this->assertSame(1, $container->invoices()->count());
        $this->assertEqualsWithDelta(3, $line->fresh()->boxes_count, 0.001);
    }

    /**
     * EN: Changing a container's invoices needs its edit permission.
     * AR: تغيير فواتير الكونتينر يحتاج صلاحية تعديله.
     */
    public function test_changing_the_invoices_needs_the_permission(): void
    {
        $container = Container::factory()->create();
        $invoice = $this->invoiceWithCartons(1);

        $this->actingAs($this->userWith('container.view'))
            ->postJson("/admin-api/containers/{$container->id}/invoices", ['ids' => [$invoice->id]])->assertForbidden();
    }

    /**
     * EN: A container that has invoices is not deleted.
     * AR: الكونتينر اللي بيه فواتير ما ينحذف.
     */
    public function test_a_container_with_invoices_is_not_deleted(): void
    {
        $container = Container::factory()->create();
        $this->invoiceWithCartons(1)->container()->associate($container)->save();

        $this->actingAs($this->userWith('container.view', 'container.delete'))
            ->deleteJson("/admin-api/containers/{$container->id}")->assertStatus(422);
        $this->assertNotSoftDeleted($container);
    }
}
