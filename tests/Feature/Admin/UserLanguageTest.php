<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLanguageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: A signed-in user who switches the language has it saved on their account.
     * AR: اليوزر الداخل من يغيّر اللغة، تنحفظ على حسابه.
     */
    public function test_switching_the_language_saves_it_on_the_signed_in_users_account(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson('/admin-api/locale', ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('locale', 'en');

        $this->assertSame('en', $user->fresh()->locale);
    }

    /**
     * EN: The language saved on the account follows the user into a new browser, where the session has no language:
     *     the admin application's page and its API both come in it.
     * AR: اللغة المحفوظة على الحساب تلحك اليوزر لمتصفح جديد ما بيه لغة بالـ session: صفحة تطبيق الإدارة والـ API مالته يجون بيها.
     */
    public function test_the_saved_language_follows_the_user_into_a_new_browser(): void
    {
        $this->withoutVite();

        $user = User::factory()->create(['is_active' => true, 'locale' => 'en']);

        $this->actingAs($user)
            ->get('/app')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false);

        $this->getJson('/admin-api/session')->assertJsonPath('locale', 'en');
    }

    /**
     * EN: Signing in switches the application to the language saved on the account, not the one of the login page.
     * AR: تسجيل الدخول يحوّل التطبيق للغة المحفوظة على الحساب، مو لغة صفحة الدخول.
     */
    public function test_signing_in_switches_to_the_accounts_saved_language(): void
    {
        $user = User::factory()->create(['is_active' => true, 'locale' => 'zh_CN']);

        $this->withSession(['locale' => 'en'])
            ->postJson('/admin-api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('locale', 'zh_CN');

        $this->getJson('/admin-api/session')->assertJsonPath('locale', 'zh_CN');
    }

    /**
     * EN: A user who never chose a language gets Arabic after signing in, even if the login page was switched to another language.
     * AR: اليوزر اللي عمره ما اختار لغة، ياخذ عربي بعد الدخول، حتى لو صفحة الدخول انحوّلت للغة ثانية.
     */
    public function test_signing_in_without_a_saved_language_is_arabic_even_if_the_login_page_was_switched(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->withSession(['locale' => 'en'])
            ->postJson('/admin-api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('locale', 'ar');

        $this->getJson('/admin-api/session')->assertJsonPath('locale', 'ar');
    }

    /**
     * EN: Signing out brings the login page back to Arabic, whatever language the account had.
     * AR: تسجيل الخروج يرجّع صفحة الدخول للعربي، مهما جانت لغة الحساب.
     */
    public function test_signing_out_goes_back_to_arabic(): void
    {
        $user = User::factory()->create(['is_active' => true, 'locale' => 'en']);

        $this->actingAs($user)
            ->postJson('/admin-api/logout')
            ->assertOk()
            ->assertJsonPath('locale', 'ar');
    }
}
