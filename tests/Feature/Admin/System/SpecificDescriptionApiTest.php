<?php

namespace Tests\Feature\Admin\System;

use App\Models\GeneralDescription;
use App\Models\Item;
use App\Models\SpecificDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificDescriptionApiTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    /**
     * EN: The item descriptions are hidden by default, shown on purpose with the filter, and the list is filtered by general description;
     *     a general description's own page lists all of its descriptions, item descriptions included.
     * AR: أوصاف المواد مخفية افتراضياً، وتطلع بالفلتر، والقائمة تتفلتر بالوصف العام؛
     *     وصفحة الوصف العام تطلع كل أوصافه، ويا أوصاف المواد.
     */
    public function test_item_descriptions_are_hidden_by_default(): void
    {
        $existing = SpecificDescription::query()->count();
        $rent = GeneralDescription::factory()->create();
        SpecificDescription::factory()->create(['general_description_id' => $rent->id, 'name' => 'Office']);
        SpecificDescription::factory()->create(['name' => 'Fuel']);
        $item = Item::factory()->create();
        $this->actingAs($this->userWith('specific_description.view'));

        $this->getJson('/admin-api/specific-descriptions')->assertOk()->assertJsonCount($existing + 2, 'data');
        $this->getJson('/admin-api/specific-descriptions?filter[item_descriptions]=only')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.can.update', false)
            ->assertJsonPath('data.0.is_item_description', true);
        $this->getJson('/admin-api/specific-descriptions?filter[item_descriptions]=with')->assertJsonCount($existing + 3, 'data');
        $this->getJson("/admin-api/specific-descriptions?filter[general_description_id]={$rent->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Office')
            ->assertJsonPath('data.0.general_description.id', $rent->id);

        $purchasesId = $item->specificDescription->general_description_id;
        $this->getJson("/admin-api/specific-descriptions?general_description_id={$purchasesId}")
            ->assertJsonPath('data.0.is_item_description', true);
    }

    /**
     * EN: A description is created under a general description that is not the purchases one; only a super user sets its transfer type.
     * AR: الوصف ينضاف تحت وصف عام مو المشتريات؛ وبس السوبر يحدد نوع التحويل.
     */
    public function test_create_and_the_transfer_type(): void
    {
        $rent = GeneralDescription::factory()->create();
        $this->actingAs($this->userWith('specific_description.create'));

        $this->postJson('/admin-api/specific-descriptions', ['general_description_id' => GeneralDescription::purchases()->id, 'name' => 'X'])
            ->assertJsonValidationErrors('general_description_id');

        $this->postJson('/admin-api/specific-descriptions', ['general_description_id' => $rent->id, 'name' => 'Office', 'transfer_type' => 'cash'])
            ->assertCreated()
            ->assertJsonPath('data.transfer_type', null);

        $this->actingAs($this->superUser())
            ->postJson('/admin-api/specific-descriptions', ['general_description_id' => $rent->id, 'name' => 'Withdraw', 'transfer_type' => 'cash'])
            ->assertCreated()
            ->assertJsonPath('data.transfer_type.value', 'cash');
    }

    /**
     * EN: A non-super edit keeps the transfer type; an item description cannot be edited or deleted.
     * AR: تعديل اليوزر العادي يخلي نوع التحويل مثل ما هو؛ ووصف المادة ما ينعدّل وما ينحذف.
     */
    public function test_update_rules(): void
    {
        $withdraw = SpecificDescription::factory()->create(['transfer_type' => 'cash']);
        $itemDescription = Item::factory()->create()->specificDescription;
        $this->actingAs($this->userWith('specific_description.update', 'specific_description.delete'));

        $this->putJson("/admin-api/specific-descriptions/{$withdraw->id}", ['general_description_id' => $withdraw->general_description_id, 'name' => 'Renamed', 'transfer_type' => null])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.transfer_type.value', 'cash');

        $this->putJson("/admin-api/specific-descriptions/{$itemDescription->id}", ['general_description_id' => $itemDescription->general_description_id, 'name' => 'X'])->assertForbidden();
        $this->deleteJson("/admin-api/specific-descriptions/{$itemDescription->id}")->assertForbidden();
        $this->deleteJson("/admin-api/specific-descriptions/{$withdraw->id}")->assertNoContent();
    }
}
