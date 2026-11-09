<?php

namespace Tests\Unit;

use App\Admin\Pages\FinancialDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A user with the financial_dashboard.view permission can open the financial dashboard.
     * AR: اليوزر اللي عنده صلاحية financial_dashboard.view يكدر يفتح اللوحة المالية.
     */
    public function test_user_with_the_permission_can_open_the_financial_dashboard(): void
    {
        $this->actingAs($this->userWithPermission())
            ->getJson('/admin-api/dashboard/financial')
            ->assertOk();
    }

    /**
     * EN: A user without the permission cannot open the financial dashboard, even by typing its address.
     * AR: اليوزر اللي ما عنده الصلاحية ما يكدر يفتح اللوحة المالية، حتى لو كتب رابطها.
     */
    public function test_user_without_the_permission_cannot_open_the_financial_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->getJson('/admin-api/dashboard/financial')
            ->assertForbidden();
    }

    /**
     * EN: A super user can open the financial dashboard without the permission.
     * AR: السوبر يوزر يكدر يفتح اللوحة المالية بدون الصلاحية.
     */
    public function test_super_user_can_open_the_financial_dashboard_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'is_super' => true]))
            ->getJson('/admin-api/dashboard/financial')
            ->assertOk();
    }

    /**
     * EN: The sidebar shows the financial dashboard link to a user with the permission.
     * AR: القائمة الجانبية تعرض رابط اللوحة المالية لليوزر اللي عنده الصلاحية.
     */
    public function test_sidebar_shows_the_link_to_a_user_with_the_permission(): void
    {
        $this->actingAs($this->userWithPermission())
            ->withSession(['locale' => 'en'])
            ->getJson('/admin-api/navigation')
            ->assertOk()
            ->assertJsonPath('groups.0.items.1', ['key' => 'financial-dashboard', 'label' => 'Financial dashboard', 'icon' => 'o-banknotes', 'path' => '/financial-dashboard']);
    }

    /**
     * EN: The sidebar hides the financial dashboard link from a user without the permission.
     * AR: القائمة الجانبية تخفي رابط اللوحة المالية عن اليوزر اللي ما عنده الصلاحية.
     */
    public function test_sidebar_hides_the_link_from_a_user_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->withSession(['locale' => 'en'])
            ->getJson('/admin-api/navigation')
            ->assertOk()
            ->assertDontSee('Financial dashboard')
            ->assertDontSee('/financial-dashboard');
    }

    /**
     * EN: The link is not in a sidebar group and comes right after the home dashboard, which stays the first page.
     * AR: الرابط مو داخل مجموعة بالقائمة الجانبية، ويجي مباشرة بعد الرئيسية، والرئيسية تبقى أول صفحة.
     */
    public function test_link_comes_right_after_the_home_dashboard(): void
    {
        $firstGroup = $this->actingAs($this->userWithPermission())
            ->getJson('/admin-api/navigation')
            ->assertOk()
            ->json('groups.0');

        $this->assertNull($firstGroup['key']);
        $this->assertSame(['home', 'financial-dashboard'], array_column($firstGroup['items'], 'key'));
    }

    /**
     * EN: The financial dashboard label in each language: the page title and the sidebar link.
     * AR: عنوان اللوحة المالية بكل لغة: عنوان الصفحة ورابط القائمة الجانبية.
     *
     * @return array<string, array{string, string}>
     */
    public static function labels(): array
    {
        return [
            'Arabic' => ['ar', 'اللوحة المالية'],
            'English' => ['en', 'Financial dashboard'],
        ];
    }

    /**
     * EN: The financial dashboard shows its title and sidebar link in the current language.
     * AR: اللوحة المالية تعرض عنوانها ورابطها باللغة الحالية.
     */
    #[DataProvider('labels')]
    public function test_labels_are_shown_in_the_current_language(string $locale, string $label): void
    {
        App::setLocale($locale);

        $this->assertSame($label, FinancialDashboard::label());
    }

    /**
     * EN: Creates an active normal user with the financial_dashboard.view permission.
     * AR: ينشئ يوزر عادي فعّال عنده صلاحية financial_dashboard.view.
     */
    private function userWithPermission(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'permission' => 'financial_dashboard.view']);

        return $user;
    }
}
