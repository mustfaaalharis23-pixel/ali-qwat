<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelLanguageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: By default, the login page is in Arabic and right-to-left.
     * AR: صفحة الدخول بشكل افتراضي بالعربي ومن اليمين لليسار.
     */
    public function test_login_page_is_arabic_and_right_to_left_by_default(): void
    {
        $this->get('/admin/login')
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
            ->get('/admin/login')
            ->assertOk()
            ->assertSee("lang=\"{$langAttribute}\"", false)
            ->assertSee('dir="ltr"', false);
    }

    /**
     * EN: The language is also applied to the panel's Livewire requests (tables, forms, validation messages), as Filament's docs recommend with persistent middleware.
     * AR: اللغة تنطبق هم على طلبات Livewire باللوحة (الجداول والفورمات ورسائل الأخطاء)، مثل ما توصي docs Filament بالـ middleware الـ persistent.
     */
    public function test_language_is_applied_to_livewire_requests(): void
    {
        $this->assertContains(SetLocale::class, Livewire::getPersistentMiddleware());
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
     * EN: The login page in each language has a link to switch to each of the other two languages, and no link to its own.
     * AR: صفحة الدخول بكل لغة بيها رابط للتحويل لكل وحدة من اللغتين الثانيات، وما بيها رابط للغتها نفسها.
     *
     * @param  array<string, string>  $otherLanguages
     */
    #[DataProvider('languagesAndTheirSwitchLinks')]
    public function test_login_page_has_links_to_switch_to_the_other_languages(string $locale, array $otherLanguages): void
    {
        $response = $this->withSession(['locale' => $locale])->get('/admin/login');

        foreach ($otherLanguages as $otherLocale => $name) {
            $response->assertSee(route('locale.switch', $otherLocale), false)->assertSee($name);
        }

        $response->assertDontSee(route('locale.switch', $locale), false);
    }

    /**
     * EN: Inside the panel, in each language, the user menu has a link to switch to each of the other two languages, and no link to its own.
     * AR: داخل اللوحة، بكل لغة، قائمة اليوزر بيها رابط للتحويل لكل وحدة من اللغتين الثانيات، وما بيها رابط للغتها نفسها.
     *
     * @param  array<string, string>  $otherLanguages
     */
    #[DataProvider('languagesAndTheirSwitchLinks')]
    public function test_panel_has_links_to_switch_to_the_other_languages(string $locale, array $otherLanguages): void
    {
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->withSession(['locale' => $locale])
            ->get('/admin')
            ->assertOk();

        foreach ($otherLanguages as $otherLocale => $name) {
            $response->assertSee(route('locale.switch', $otherLocale), false)->assertSee($name);
        }

        $response->assertDontSee(route('locale.switch', $locale), false);
    }
}
