<?php

namespace Tests\Feature\Admin\System;

use App\Enums\InvoiceType;
use App\Models\GeneralDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralDescriptionApiTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    /**
     * EN: The list is searched by name and filtered by type, with the purchases description marked.
     * AR: القائمة تنبحث بالاسم وتتفلتر بالنوع، ووصف المشتريات معلّم.
     */
    public function test_the_list_is_searched_and_filtered(): void
    {
        GeneralDescription::factory()->create(['name' => 'Rent', 'type' => InvoiceType::Output]);
        GeneralDescription::factory()->create(['name' => 'Rental income', 'type' => InvoiceType::Input]);

        $this->actingAs($this->userWith())->getJson('/admin-api/general-descriptions')->assertForbidden();

        $this->actingAs($this->userWith('general_description.view'))
            ->getJson('/admin-api/general-descriptions?search=rent&filter[type]=input')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rental income')
            ->assertJsonPath('data.0.type.value', 'input')
            ->assertJsonPath('can.create', false);

        $purchases = GeneralDescription::purchases();
        $this->getJson("/admin-api/general-descriptions/{$purchases->id}")
            ->assertOk()
            ->assertJsonPath('data.is_purchases_description', true);
    }

    /**
     * EN: A general description is created and edited with its name, type, and capital movement flag.
     * AR: الوصف العام ينضاف وينعدّل باسمه ونوعه وعلامة حركة رأس المال.
     */
    public function test_create_and_update(): void
    {
        $this->actingAs($this->userWith('general_description.view', 'general_description.create', 'general_description.update'));

        $id = $this->postJson('/admin-api/general-descriptions', ['name' => 'Capital', 'type' => 'input', 'is_capital_movement' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_capital_movement', true)
            ->json('data.id');

        $this->postJson('/admin-api/general-descriptions', ['name' => '', 'type' => 'sideways'])->assertJsonValidationErrors(['name', 'type']);

        $this->putJson("/admin-api/general-descriptions/{$id}", ['name' => 'Partners', 'type' => 'output', 'is_capital_movement' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Partners')
            ->assertJsonPath('data.type.value', 'output');
    }

    /**
     * EN: The purchases description is never deleted from the screen and never becomes a capital movement.
     * AR: وصف المشتريات ما ينحذف من الشاشة وما يصير حركة رأس مال.
     */
    public function test_the_purchases_description_is_protected(): void
    {
        $purchases = GeneralDescription::purchases();
        $this->actingAs($this->superUser());

        $this->getJson("/admin-api/general-descriptions/{$purchases->id}")
            ->assertJsonPath('data.can.delete', false)
            ->assertJsonPath('data.can.update', true);

        $this->deleteJson("/admin-api/general-descriptions/{$purchases->id}")->assertForbidden();
        $this->deleteJson("/admin-api/general-descriptions/{$purchases->id}/force")->assertForbidden();

        $this->putJson("/admin-api/general-descriptions/{$purchases->id}", ['name' => 'Purchases', 'type' => 'output', 'is_capital_movement' => true])->assertOk();
        $this->assertFalse($purchases->refresh()->is_capital_movement);
        $this->assertTrue($purchases->is_purchases_description);
    }

    /**
     * EN: A general description is deleted, shown with the deleted filter, and restored.
     * AR: الوصف العام ينحذف، ويطلع ويا فلتر المحذوفات، ويرجع.
     */
    public function test_delete_and_restore(): void
    {
        $rent = GeneralDescription::factory()->create(['name' => 'Rent']);
        $this->actingAs($this->userWith('general_description.view', 'general_description.delete', 'general_description.restore'));

        $this->deleteJson("/admin-api/general-descriptions/{$rent->id}")->assertNoContent();
        $this->getJson('/admin-api/general-descriptions?trashed=only')->assertJsonCount(1, 'data')->assertJsonPath('data.0.can.restore', true);
        $this->postJson("/admin-api/general-descriptions/{$rent->id}/restore")->assertOk();
        $this->assertNotSoftDeleted($rent);
    }
}
