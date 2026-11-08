<?php

namespace Tests\Feature\Admin\Catalog;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LensApiTest extends TestCase
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
     * EN: An unknown lens is not found, and a lens opens only with its own permission.
     * AR: العدسة غير المعروفة ما تنلگى، والعدسة تنفتح بس بصلاحيتها.
     */
    public function test_a_lens_needs_its_permission(): void
    {
        $this->actingAs($this->userWith('lenses.items_without_supplier'));

        $this->getJson('/admin-api/lenses/nothing-here')->assertNotFound();
        $this->getJson('/admin-api/lenses/items-without-sales')->assertForbidden();
        $this->getJson('/admin-api/lenses/items-without-supplier')->assertOk();
    }

    /**
     * EN: A lens built on the cost needs the cost permission as well as its own.
     * AR: العدسة المبنية عالكلفة تحتاج صلاحية الكلفة ويا صلاحيتها.
     */
    public function test_a_cost_lens_needs_the_cost_permission(): void
    {
        $this->actingAs($this->userWith('lenses.items_sold_at_a_loss'))->getJson('/admin-api/lenses/items-sold-at-a-loss')->assertForbidden();
        $this->actingAs($this->userWith('lenses.items_sold_at_a_loss', 'item.view_cost_price'))->getJson('/admin-api/lenses/items-sold-at-a-loss')->assertOk();
    }

    /**
     * EN: The lens answers its texts, its columns as the lens declares them, its rows from its own query (active items only), its actions, and its cards.
     * AR: العدسة ترجع نصوصها، وأعمدتها مثل ما معرّفة، وصفوفها من استعلامها (المواد الفعالة بس)، وأكشناتها، وكروتها.
     */
    public function test_the_lens_answers_its_columns_rows_and_actions(): void
    {
        Item::factory()->create(['name' => 'Without', 'sale_price' => 10]);
        Item::factory()->inactive()->create(['name' => 'Inactive without']);
        Item::factory()->create(['name' => 'With', 'supplier_id' => Supplier::factory()->create()->id]);

        $response = $this->actingAs($this->userWith('lenses.items_without_supplier', 'item.update'))
            ->getJson('/admin-api/lenses/items-without-supplier')
            ->assertOk()
            ->assertJsonPath('lens.key', 'items-without-supplier')
            ->assertJsonPath('lens.label', __('filament/lenses.items_without_supplier.label'))
            ->assertJsonPath('lens.guidance', __('filament/lenses.items_without_supplier.guidance'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cells.name.value', 'Without')
            ->assertJsonPath('data.0.cells.sale_price.value', 10)
            ->assertJsonPath('data.0.cells.is_active.value', true)
            ->assertJsonPath('data.0.can.update', true);

        $this->assertSame(['id', 'image', 'name', 'sale_price', 'cost_price', 'is_active'], array_column($response->json('columns'), 'name'));
        $this->assertSame(['type' => 'image'], array_intersect_key($response->json('columns.1'), ['type' => true]));
        $this->assertSame('boolean', $response->json('columns.5.type'));
        $this->assertTrue($response->json('columns.3.sortable'));
        $this->assertTrue($response->json('columns.3.numeric'));
        $this->assertSame(['setSupplier'], $response->json('actions'));
        $this->assertSame(['setSupplierForSelected'], $response->json('bulk_actions'));
        $this->assertSame('item_without_supplier_count', $response->json('metrics.0.key'));
    }

    /**
     * EN: The lens rows are searched on its searchable columns and sorted on its sortable ones.
     * AR: صفوف العدسة تنبحث بأعمدتها القابلة للبحث وتترتب بأعمدتها القابلة للترتيب.
     */
    public function test_the_lens_is_searched_and_sorted(): void
    {
        Item::factory()->create(['name' => 'Alpha', 'sale_price' => 5]);
        Item::factory()->create(['name' => 'Beta', 'sale_price' => 50]);
        Item::factory()->create(['name' => 'Gamma', 'sale_price' => 20]);
        $this->actingAs($this->userWith('lenses.items_without_sales'));

        $response = $this->getJson('/admin-api/lenses/items-without-sales?sort=sale_price&direction=desc')->assertOk();
        $this->assertSame(['Beta', 'Gamma', 'Alpha'], array_column(array_column(array_column($response->json('data'), 'cells'), 'name'), 'value'));

        $this->getJson('/admin-api/lenses/items-without-sales?search=gam')->assertJsonCount(1, 'data');
    }

    /**
     * EN: A column the lens works out is sent with its value and its color, such as the margin percentage of the low margin lens.
     * AR: العمود اللي تحسبه العدسة ينبعث بقيمته ولونه، مثل نسبة الهامش بعدسة الهامش الواطي.
     */
    public function test_a_worked_out_column_has_its_value_and_color(): void
    {
        $item = Item::factory()->create(['sale_price' => 100]);
        $item->forceFill(['cost_price' => 90])->saveQuietly();

        $this->actingAs($this->userWith('lenses.items_with_a_low_margin', 'item.view_cost_price', 'item.update'))
            ->getJson('/admin-api/lenses/items-with-a-low-margin')
            ->assertOk()
            ->assertJsonPath('data.0.cells.profit_margin.value', 10)
            ->assertJsonPath('data.0.cells.profit_margin_percentage.value', '10%')
            ->assertJsonPath('data.0.cells.profit_margin_percentage.color', 'danger')
            ->assertJsonPath('actions', ['setSalePrice']);
    }
}
