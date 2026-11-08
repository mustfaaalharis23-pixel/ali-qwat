<?php

namespace Tests\Feature\Admin\Purchases;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierApiTest extends TestCase
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

    private function debtOf(Supplier $supplier, float $amount, string $date): Invoice
    {
        $invoice = Invoice::factory()->purchase()->create(['supplier_id' => $supplier->id, 'date' => $date]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'boxes_count' => 1, 'pieces_per_packet' => 1, 'boxes_per_carton' => 1, 'carton_cbm' => 0.05, 'unit_cost' => $amount, 'price' => $amount]);

        return $invoice->fresh();
    }

    /**
     * EN: The suppliers are searched by their Arabic or Chinese name.
     * AR: الموردين ينبحثون باسمهم العربي أو الصيني.
     */
    public function test_the_list_is_searched(): void
    {
        Supplier::factory()->create(['name_ar' => 'علي', 'name_zh' => '阿里']);
        Supplier::factory()->create(['name_ar' => 'حسن', 'name_zh' => '哈桑']);

        $this->actingAs($this->userWith('supplier.view'))
            ->getJson('/admin-api/suppliers?search='.rawurlencode('阿里'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name_ar', 'علي');
    }

    /**
     * EN: A supplier is created with a contract uploaded first, and an Arabic name is required.
     * AR: المورد ينضاف ويا عقد ينرفع أول، والاسم العربي مطلوب.
     */
    public function test_create_with_a_contract(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWith('supplier.view', 'supplier.create'));

        $path = $this->postJson('/admin-api/suppliers/uploads', ['file' => UploadedFile::fake()->create('c.pdf', 100, 'application/pdf')])->assertOk()->json('path');
        $this->postJson('/admin-api/suppliers', ['name_zh' => 'x'])->assertJsonValidationErrors('name_ar');
        $id = $this->postJson('/admin-api/suppliers', ['name_ar' => 'علي', 'email' => 'a@b.c', 'contract' => $path])->assertCreated()->json('data.id');

        $this->assertSame($path, Supplier::query()->findOrFail($id)->contract);
    }

    /**
     * EN: The supplier's page shows its totals, its invoices, and its items.
     * AR: صفحة المورد تبين مجاميعه وفواتيره ومواده.
     */
    public function test_the_page_totals_invoices_and_items(): void
    {
        $supplier = Supplier::factory()->create();
        $this->debtOf($supplier, 1000, '2026-10-01');
        Item::factory()->create(['supplier_id' => $supplier->id]);
        $this->actingAs($this->userWith('supplier.view', 'invoice.view', 'item.view'));

        $this->getJson("/admin-api/suppliers/{$supplier->id}")->assertOk()->assertJsonPath('data.id', $supplier->id);
        $this->getJson("/admin-api/suppliers/{$supplier->id}/metrics/debt")->assertOk()->assertJsonPath('value.raw', 1000);
        $this->getJson("/admin-api/suppliers/{$supplier->id}/metrics/items_count")->assertOk()->assertJsonPath('value.raw', 1);
        $this->getJson("/admin-api/suppliers/{$supplier->id}/invoices")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/admin-api/suppliers/{$supplier->id}/items")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/admin-api/suppliers/metrics/total_debt')->assertOk()->assertJsonPath('value.raw', 1000);
        $this->getJson('/admin-api/suppliers/chart')->assertOk()->assertJsonPath('parts.0.value', 1000);
    }

    /**
     * EN: One amount is paid on the supplier's invoices from the oldest; more than the debt is refused.
     * AR: مبلغ واحد ينسدد على فواتير المورد من الأقدم، والأكثر من الدين ينرفض.
     */
    public function test_a_payoff_is_distributed_from_the_oldest(): void
    {
        $supplier = Supplier::factory()->create();
        $old = $this->debtOf($supplier, 300, '2026-09-01');
        $new = $this->debtOf($supplier, 500, '2026-10-01');
        $this->actingAs($this->userWith('supplier.view', 'payoff.create'));

        $this->getJson("/admin-api/suppliers/{$supplier->id}/debt")->assertOk()->assertJsonPath('debt', 800);
        $this->postJson("/admin-api/suppliers/{$supplier->id}/distribute-payoff", ['amount' => 900, 'date' => '2026-11-01'])->assertJsonValidationErrors('amount');
        $this->postJson("/admin-api/suppliers/{$supplier->id}/distribute-payoff", ['amount' => 400, 'date' => '2026-11-01'])->assertOk();

        $this->assertEqualsWithDelta(0, $old->fresh()->remaining, 0.001);
        $this->assertEqualsWithDelta(400, $new->fresh()->remaining, 0.001);
    }

    /**
     * EN: Paying needs the payoff create permission.
     * AR: التسديد يحتاج صلاحية إضافة التسديدات.
     */
    public function test_paying_needs_the_permission(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->userWith('supplier.view'))
            ->postJson("/admin-api/suppliers/{$supplier->id}/distribute-payoff", ['amount' => 1, 'date' => '2026-11-01'])->assertForbidden();
    }
}
