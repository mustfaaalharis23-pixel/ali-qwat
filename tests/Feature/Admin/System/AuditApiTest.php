<?php

namespace Tests\Feature\Admin\System;

use App\Models\Audit;
use App\Models\Color;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    /**
     * EN: The audits list needs the audit view permission, is newest first, and is filtered by event and record type.
     * AR: قائمة السجلات تحتاج صلاحية عرض السجل، الأحدث أول، وتتفلتر بالحدث ونوع السجل.
     */
    public function test_the_list_is_filtered(): void
    {
        $user = $this->userWith('audit.view');
        $nobody = $this->userWith();
        $this->actingAs($user);
        $color = Color::factory()->create(['name' => 'Red']);
        $color->update(['name' => 'Blue']);
        GeneralDescription::factory()->create();

        $this->actingAs($nobody)->getJson('/admin-api/audits')->assertForbidden();
        $this->actingAs($user);

        $this->getJson('/admin-api/audits?filter[event]=updated&filter[subject_type]='.urlencode(Color::class))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event.value', 'updated')
            ->assertJsonPath('data.0.causer.name', $user->name)
            ->assertJsonPath('data.0.subject_id', $color->id);

        $this->getJson('/admin-api/audits?filter[subject_type]='.urlencode(Color::class))->assertJsonCount(2, 'data');
        $this->getJson("/admin-api/audits?filter[causer]={$user->id}")->assertJsonCount(3, 'data');
        $this->getJson('/admin-api/audits?created_from='.today()->addDay()->toDateString())->assertJsonCount(0, 'data');

        $filters = $this->getJson('/admin-api/audits/filters')->assertOk();
        $this->assertContains(Color::class, array_column($filters->json('subject_types'), 'value'));
    }

    /**
     * EN: The audits of one record, for the audits tab of any record page, by its type and number.
     * AR: سجلات سجل واحد، لتبويب السجلات بأي صفحة، بنوعه ورقمه.
     */
    public function test_the_audits_of_one_record(): void
    {
        $this->actingAs($this->userWith('audit.view'));
        $red = Color::factory()->create();
        $red->update(['name' => 'Crimson']);
        Color::factory()->create();

        $this->getJson('/admin-api/audits?subject_type=color&subject_id='.$red->id)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/admin-api/audits?subject_type='.urlencode(Color::class).'&subject_id='.$red->id)->assertJsonCount(2, 'data');
    }

    /**
     * EN: Every record page with an audits tab (orders, customers, items, suppliers, containers, invoices) finds its audits by its short type name.
     * AR: كل صفحة سجل بيها تبويب السجلات (الطلبات، الزبائن، المواد، الموردين، الحاويات، الفواتير) تلگى سجلاتها باسم نوعها المختصر.
     */
    public function test_every_record_page_type_is_accepted(): void
    {
        $this->actingAs($this->userWith('audit.view'));
        $records = [
            'order' => Order::factory()->create(),
            'customer' => Customer::factory()->create(),
            'item' => Item::factory()->create(),
            'supplier' => Supplier::factory()->create(),
            'container' => Container::factory()->create(),
            'invoice' => Invoice::factory()->create(),
        ];

        foreach ($records as $type => $record) {
            $count = Audit::query()->where('subject_type', $record->getMorphClass())->where('subject_id', $record->getKey())->count();
            $this->assertGreaterThan(0, $count, $type);
            $this->getJson("/admin-api/audits?subject_type={$type}&subject_id={$record->getKey()}")
                ->assertOk()
                ->assertJsonCount($count, 'data')
                ->assertJsonPath('data.0.subject_id', $record->getKey());
        }
    }

    /**
     * EN: An audit's page lists each changed field with its label, the value before, and the value after.
     * AR: صفحة السجل تطلع كل حقل تغيّر بعنوانه، والقيمة قبل، والقيمة بعد.
     */
    public function test_show_lists_the_changes(): void
    {
        $this->actingAs($this->userWith('audit.view'));
        $color = Color::factory()->create(['name' => 'Red']);
        $color->update(['name' => 'Blue']);
        $audit = Audit::query()->where('event', 'updated')->where('subject_type', Color::class)->firstOrFail();

        $response = $this->getJson("/admin-api/audits/{$audit->id}")->assertOk();
        $name = collect($response->json('data.changes'))->firstWhere('attribute', __('filament/resources/color.fields.name'));
        $this->assertSame(['attribute' => __('filament/resources/color.fields.name'), 'old' => 'Red', 'new' => 'Blue'], $name);
        $response->assertJsonPath('data.subject_type_label', __('filament/resources/color.label'));
    }
}
