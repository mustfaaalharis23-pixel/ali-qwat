<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * EN: By default, the login page is in Arabic and right-to-left.
     * AR: صفحة الدخول بشكل افتراضي بالعربي ومن اليمين لليسار.
     */
    public function test_login_page_is_arabic_and_right_to_left_by_default(): void
    {
        $this->get('/app/login')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    /**
     * EN: The left-to-right languages and how each one is written in the page's lang attribute.
     * AR: اللغات اللي من اليسار لليمين وشلون تنكتب كل وحدة بخاصية lang مال الصفحة.
     *
     * @return array<string, array{string, string}>
     */
    public static function leftToRightLanguages(): array
    {
        return [
            'English' => ['en', 'en'],
            'Simplified Chinese' => ['zh_CN', 'zh-CN'],
        ];
    }

    /**
     * EN: After choosing English or Chinese, the login page is in that language and left-to-right.
     * AR: بعد اختيار الإنكليزي أو الصيني، صفحة الدخول بهاي اللغة ومن اليسار لليمين.
     */
    #[DataProvider('leftToRightLanguages')]
    public function test_login_page_is_left_to_right_after_choosing_a_left_to_right_language(string $locale, string $langAttribute): void
    {
        $this->withSession(['locale' => $locale])
            ->get('/app/login')
            ->assertOk()
            ->assertSee("lang=\"{$langAttribute}\"", false)
            ->assertSee('dir="ltr"', false);
    }

    /**
     * EN: The chosen language is also applied to the admin application's API requests (its texts and validation messages).
     * AR: اللغة المختارة تنطبق هم على طلبات الـ API مال تطبيق الإدارة (نصوصه ورسائل الأخطاء).
     */
    public function test_language_is_applied_to_the_api_requests(): void
    {
        $this->withSession(['locale' => 'en'])
            ->getJson('/admin-api/session')
            ->assertOk()
            ->assertJsonPath('locale', 'en');

        $this->withSession(['locale' => 'en'])
            ->postJson('/admin-api/login', [])
            ->assertJsonValidationErrors(['email' => __('validation.required', ['attribute' => 'email'], 'en')]);
    }

    /**
     * EN: Each language, with the other two languages it offers to switch to, each named in its own language.
     * AR: كل لغة، ويا اللغتين الثانيات اللي تعرض التحويل الهن، كل وحدة باسمها بلغتها.
     *
     * @return array<string, array{string, array<string, string>}>
     */
    public static function languagesAndTheirSwitchLinks(): array
    {
        return [
            'Arabic' => ['ar', ['en' => 'English', 'zh_CN' => '简体中文']],
            'English' => ['en', ['ar' => 'العربية', 'zh_CN' => '简体中文']],
            'Simplified Chinese' => ['zh_CN', ['ar' => 'العربية', 'en' => 'English']],
        ];
    }

    /**
     * EN: On the login page, in each language, the other two languages are offered to switch to, each named in its own language,
     *     and the current language is the one chosen.
     * AR: بصفحة الدخول، بكل لغة، تنعرض اللغتين الثانيات للتحويل، كل وحدة باسمها بلغتها، واللغة الحالية هي المختارة.
     *
     * @param  array<string, string>  $otherLanguages
     */
    #[DataProvider('languagesAndTheirSwitchLinks')]
    public function test_login_page_has_links_to_switch_to_the_other_languages(string $locale, array $otherLanguages): void
    {
        $response = $this->withSession(['locale' => $locale])->getJson('/admin-api/session')->assertOk()->assertJsonPath('locale', $locale);

        foreach ($otherLanguages as $otherLocale => $name) {
            $response->assertJsonPath("locales.{$otherLocale}", $name);
        }
    }

    /**
     * EN: Inside the admin application, in each language, the signed-in user is offered the other two languages, each named in its
     *     own language, and switching to one keeps it in the session.
     * AR: داخل تطبيق الإدارة، بكل لغة، اليوزر الداخل تنعرضله اللغتين الثانيات، كل وحدة باسمها بلغتها، والتحويل لوحدة منها ينحفظ بالجلسة.
     *
     * @param  array<string, string>  $otherLanguages
     */
    #[DataProvider('languagesAndTheirSwitchLinks')]
    public function test_panel_has_links_to_switch_to_the_other_languages(string $locale, array $otherLanguages): void
    {
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->withSession(['locale' => $locale])
            ->getJson('/admin-api/session')
            ->assertOk()
            ->assertJsonPath('locale', $locale);

        foreach ($otherLanguages as $otherLocale => $name) {
            $response->assertJsonPath("locales.{$otherLocale}", $name);
        }

        $otherLocale = array_key_first($otherLanguages);

        $this->postJson('/admin-api/locale', ['locale' => $otherLocale])->assertJsonPath('locale', $otherLocale);
        $this->assertSame($otherLocale, session('locale'));
    }
}
