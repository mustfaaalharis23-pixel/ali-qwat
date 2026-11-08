<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A user with the financial dashboard permission gets its cards, each card with its number and its ranges.
     * AR: اليوزر اللي عنده صلاحية اللوحة المالية ياخذ بطاقاتها، كل بطاقة برقمها ومدياتها.
     */
    public function test_the_financial_cards_come_with_numbers_and_ranges(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => 'financial_dashboard.view']);

        $cards = $this->actingAs($user)->getJson('/admin-api/dashboard/financial')->assertOk()->json('cards');
        $this->assertContains('revenues', $cards);

        $this->getJson('/admin-api/dashboard/metrics/revenues?range=last_month')
            ->assertOk()
            ->assertJsonPath('range', 'last_month')
            ->assertJsonStructure(['label', 'value' => ['number', 'currency'], 'ranges', 'explanation', 'example']);
    }

    /**
     * EN: A range the card does not offer falls back to its default range; an unknown card is not found.
     * AR: المدى اللي البطاقة ما تعرضه يرجع لمداها الافتراضي، والبطاقة المجهولة ما تنلگه.
     */
    public function test_an_unknown_range_or_card(): void
    {
        $super = User::factory()->create(['is_active' => true, 'is_super' => true]);

        $this->actingAs($super)->getJson('/admin-api/dashboard/metrics/revenues?range=nonsense')->assertOk()->assertJsonPath('range', 'this_month');
        $this->getJson('/admin-api/dashboard/metrics/nope')->assertNotFound();
    }

    /**
     * EN: Without the permission the financial dashboard is forbidden.
     * AR: بدون الصلاحية اللوحة المالية ممنوعة.
     */
    public function test_the_financial_dashboard_needs_its_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->getJson('/admin-api/dashboard/financial')->assertForbidden();
        $this->getJson('/admin-api/dashboard/metrics/revenues')->assertForbidden();
    }

    /**
     * EN: The monitoring dashboard lists the lenses with their counts for a super user.
     * AR: لوحة المراقبة تعرض العدسات وأعدادها للسوبر.
     */
    public function test_the_monitoring_cards(): void
    {
        $super = User::factory()->create(['is_active' => true, 'is_super' => true]);

        $cards = $this->actingAs($super)->getJson('/admin-api/dashboard/monitoring')->assertOk()->json('cards');
        $this->assertCount(6, $cards);
        $this->assertArrayHasKey('count', $cards[0]);
    }

    /**
     * EN: The home reminders are hidden from a user who may not see customer payments.
     * AR: تذكيرات الصفحة الرئيسية مخفية عن اليوزر اللي ما يگدر يشوف دفعات الزبائن.
     */
    public function test_the_reminders_follow_the_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $super = User::factory()->create(['is_active' => true, 'is_super' => true]);

        $this->actingAs($user)->getJson('/admin-api/dashboard/reminders')->assertJsonPath('visible', false);
        $this->actingAs($super)->getJson('/admin-api/dashboard/reminders')->assertJsonPath('visible', true);
    }
}
