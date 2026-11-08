<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\InvoiceItemGroup;
use App\Models\LensPower;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LensPowerApiTest extends TestCase
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
     * EN: The lens powers list goes from the lowest power to the highest, with the lines of each power.
     * AR: قائمة الدرجات تبدي من أصغر درجة لأكبر درجة، ويا عدد بنود كل درجة.
     */
    public function test_the_list_is_sorted_by_value(): void
    {
        LensPower::factory()->create(['value' => 2]);
        $used = LensPower::factory()->create(['value' => 0.5]);
        InvoiceItemGroup::factory()->create(['lens_power_id' => $used->id]);

        $response = $this->actingAs($this->userWith('lens_power.view'))->getJson('/admin-api/lens-powers')->assertOk();

        $this->assertSame(['0.50', '2.00'], array_column($response->json('data'), 'value'));
        $this->assertSame(1, $response->json('data.0.invoice_item_groups_count'));
        $this->assertTrue($response->json('data.0.is_used'));
        $this->assertFalse($response->json('data.1.is_used'));
    }

    /**
     * EN: A power is a number of up to two decimals, and no two powers are the same.
     * AR: الدرجة رقم بيه لحد مرتبتين عشريتين، وما تتكرر درجتين.
     */
    public function test_create_and_update_validate_the_value(): void
    {
        LensPower::factory()->create(['value' => 1]);
        $this->actingAs($this->userWith('lens_power.view', 'lens_power.create', 'lens_power.update'));

        $this->postJson('/admin-api/lens-powers', ['value' => 1])->assertJsonValidationErrors('value');
        $this->postJson('/admin-api/lens-powers', ['value' => 1.255])->assertJsonValidationErrors('value');
        $id = $this->postJson('/admin-api/lens-powers', ['value' => 1.25])->assertCreated()->json('data.id');
        $this->putJson("/admin-api/lens-powers/{$id}", ['value' => 1.75])->assertOk()->assertJsonPath('data.value', '1.75');
    }

    /**
     * EN: Without the create permission a power cannot be added.
     * AR: بدون صلاحية الإضافة ما تنضاف درجة.
     */
    public function test_create_needs_the_permission(): void
    {
        $this->actingAs($this->userWith('lens_power.view'))->postJson('/admin-api/lens-powers', ['value' => 1])->assertForbidden();
    }

    /**
     * EN: A power on purchase lines cannot be deleted; a free one is deleted and restored.
     * AR: الدرجة اللي على بنود شراء ما تنحذف؛ والدرجة الفاضية تنحذف وترجع.
     */
    public function test_delete_and_restore(): void
    {
        $used = LensPower::factory()->create();
        InvoiceItemGroup::factory()->create(['lens_power_id' => $used->id]);
        $free = LensPower::factory()->create();
        $this->actingAs($this->userWith('lens_power.view', 'lens_power.delete', 'lens_power.restore'));

        $this->deleteJson("/admin-api/lens-powers/{$used->id}")->assertUnprocessable();
        $this->assertNotSoftDeleted($used);

        $this->deleteJson("/admin-api/lens-powers/{$free->id}")->assertNoContent();
        $this->getJson('/admin-api/lens-powers?trashed=only')->assertJsonCount(1, 'data')->assertJsonPath('data.0.can.restore', true);
        $this->postJson("/admin-api/lens-powers/{$free->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($free);
    }
}
