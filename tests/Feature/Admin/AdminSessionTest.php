<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: The admin application's page loads for anyone; the browser then asks who is signed in.
     * AR: صفحة تطبيق الإدارة تفتح لأي أحد، وبعدين المتصفح يسأل منو داخل.
     */
    public function test_the_app_page_loads_on_any_path(): void
    {
        $this->withoutVite();

        $this->get('/app')->assertOk()->assertSee('id="root"', false);
        $this->get('/app/orders/5/edit')->assertOk();
    }

    /**
     * EN: An active user signs in with their email and password and the session knows them.
     * AR: اليوزر الفعال يدخل بإيميله والباسورد والجلسة تعرفه.
     */
    public function test_an_active_user_signs_in(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->postJson('/admin-api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->getJson('/admin-api/session')->assertJsonPath('user.id', $user->id);
    }

    /**
     * EN: An inactive user and a wrong password get the same error, and nobody is signed in.
     * AR: اليوزر الموقوف والباسورد الغلط ياخذون نفس الخطأ، وماكو أحد يدخل.
     */
    public function test_an_inactive_user_or_a_wrong_password_is_rejected(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        $active = User::factory()->create(['is_active' => true]);

        $this->postJson('/admin-api/login', ['email' => $inactive->email, 'password' => 'password'])->assertJsonValidationErrors('email');
        $this->postJson('/admin-api/login', ['email' => $active->email, 'password' => 'wrong'])->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    /**
     * EN: A guest cannot reach the admin API behind the sign-in.
     * AR: الزائر ما يوصل لواجهة الإدارة اللي ورا تسجيل الدخول.
     */
    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/admin-api/navigation')->assertUnauthorized();
        $this->getJson('/admin-api/dashboard/financial')->assertUnauthorized();
    }

    /**
     * EN: The chosen language is kept in the session and the texts come in it.
     * AR: اللغة المختارة تنحفظ بالجلسة والنصوص تجي بيها.
     */
    public function test_the_language_is_switched_and_translations_are_flat(): void
    {
        $this->postJson('/admin-api/locale', ['locale' => 'en'])->assertJsonPath('locale', 'en');
        $this->postJson('/admin-api/locale', ['locale' => 'fr'])->assertJsonValidationErrors('locale');

        $strings = $this->getJson('/admin-api/translations/ar')->assertOk()->json();
        $this->assertSame(__('filament/navigation.groups.sales', locale: 'ar'), $strings['filament/navigation.groups.sales']);
    }

    /**
     * EN: The sidebar shows a super user every screen, and a user without permissions only the home page.
     * AR: القائمة الجانبية تبيّن للسوبر كل الشاشات، واليوزر اللي ما عنده صلاحيات بس الصفحة الرئيسية.
     */
    public function test_the_navigation_follows_the_permissions(): void
    {
        $super = User::factory()->create(['is_active' => true, 'is_super' => true]);
        $nobody = User::factory()->create(['is_active' => true]);

        $paths = fn (User $user): array => collect($this->actingAs($user)->getJson('/admin-api/navigation')->assertOk()->json('groups'))
            ->flatMap(fn (array $group): array => array_column($group['items'], 'path'))->all();

        $this->assertContains('/orders', $paths($super));
        $this->assertContains('/financial-dashboard', $paths($super));
        $this->assertNotContains('/orders', $paths($nobody));
    }

    /**
     * EN: The select choices are searched by name, read back by their ids, and an unknown kind is not found.
     * AR: خيارات القوائم تنبحث بالاسم، وترجع بأرقامها، والنوع المجهول ما ينلگه.
     */
    public function test_the_select_options(): void
    {
        $user = User::factory()->create(['is_active' => true, 'name' => 'Zainab']);
        User::factory()->create(['is_active' => true, 'name' => 'Ali']);

        $this->actingAs($user)->getJson('/admin-api/options/users?search=zai')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'Zainab');
        $this->getJson('/admin-api/options/users?ids[]='.$user->id)->assertJsonPath('data.0.value', $user->id);
        $this->getJson('/admin-api/options/nothing')->assertNotFound();
    }
}
