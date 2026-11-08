<?php

namespace Tests\Feature\Admin\Purchases;

use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceCategoryApiTest extends TestCase
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
     * EN: The categories are listed with their invoice counts, and a used category offers no delete.
     * AR: الأصناف تنعرض ويا عدد فواتيرها، والصنف المستخدم ما بيه حذف.
     */
    public function test_the_list_counts_the_invoices(): void
    {
        $used = InvoiceCategory::factory()->create(['name' => 'Frames']);
        InvoiceCategory::factory()->create(['name' => 'Lenses']);
        Invoice::factory()->purchase()->create(['invoice_category_id' => $used->id]);

        $response = $this->actingAs($this->userWith('invoice_category.view', 'invoice_category.delete'))
            ->getJson('/admin-api/invoice-categories?sort=name&direction=asc')->assertOk();

        $this->assertSame([1, 0], array_column($response->json('data'), 'invoices_count'));
        $this->assertFalse($response->json('data.0.can.delete'));
        $this->assertTrue($response->json('data.1.can.delete'));
    }

    /**
     * EN: A category is created and renamed with a unique name.
     * AR: الصنف ينضاف ويتغير اسمه باسم ما يتكرر.
     */
    public function test_create_and_update_with_a_unique_name(): void
    {
        InvoiceCategory::factory()->create(['name' => 'Frames']);
        $this->actingAs($this->userWith('invoice_category.view', 'invoice_category.create', 'invoice_category.update'));

        $id = $this->postJson('/admin-api/invoice-categories', ['name' => 'Lenses'])->assertCreated()->json('data.id');
        $this->postJson('/admin-api/invoice-categories', ['name' => 'Frames'])->assertJsonValidationErrors('name');
        $this->putJson("/admin-api/invoice-categories/{$id}", ['name' => 'Contact lenses'])->assertOk()->assertJsonPath('data.name', 'Contact lenses');
    }

    /**
     * EN: An unused category is deleted and restored; a used one is refused.
     * AR: الصنف الغير مستخدم ينحذف ويرجع، والمستخدم ينرفض.
     */
    public function test_delete_and_restore(): void
    {
        $free = InvoiceCategory::factory()->create();
        $used = InvoiceCategory::factory()->create();
        Invoice::factory()->purchase()->create(['invoice_category_id' => $used->id]);
        $this->actingAs($this->userWith('invoice_category.view', 'invoice_category.delete', 'invoice_category.restore'));

        $this->deleteJson("/admin-api/invoice-categories/{$used->id}")->assertStatus(422);
        $this->deleteJson("/admin-api/invoice-categories/{$free->id}")->assertNoContent();
        $this->postJson("/admin-api/invoice-categories/{$free->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($free);
    }
}
