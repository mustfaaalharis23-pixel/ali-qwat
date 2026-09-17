<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelLanguageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EN: By default, the login page is in English and left-to-right.
     * AR: صفحة الدخول بشكل افتراضي بالإنكليزي ومن اليسار لليمين.
     */
    public function test_login_page_is_english_and_left_to_right_by_default(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false);
    }

    /**
     * EN: After choosing Arabic, the login page is in Arabic and right-to-left.
     * AR: بعد اختيار العربي، صفحة الدخول بالعربي ومن اليمين لليسار.
     */
    public function test_login_page_is_arabic_and_right_to_left_after_choosing_arabic(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
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
     * EN: The English login page has a link, named "العربية", to switch to Arabic.
     * AR: صفحة الدخول بالإنكليزي بيها رابط اسمه "العربية" للتحويل للعربي.
     */
    public function test_english_login_page_has_a_link_to_switch_to_arabic(): void
    {
        $this->get('/admin/login')
            ->assertSee(route('locale.switch', 'ar'), false)
            ->assertSee('العربية');
    }

    /**
     * EN: The Arabic login page has a link, named "English", to switch to English.
     * AR: صفحة الدخول بالعربي بيها رابط اسمه "English" للتحويل للإنكليزي.
     */
    public function test_arabic_login_page_has_a_link_to_switch_to_english(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/admin/login')
            ->assertSee(route('locale.switch', 'en'), false)
            ->assertSee('English');
    }

    /**
     * EN: Inside the panel in English, the user menu has a link, named "العربية", to switch to Arabic.
     * AR: داخل اللوحة بالإنكليزي، قائمة اليوزر بيها رابط اسمه "العربية" للتحويل للعربي.
     */
    public function test_english_panel_has_a_link_to_switch_to_arabic(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('locale.switch', 'ar'), false)
            ->assertSee('العربية');
    }

    /**
     * EN: Inside the panel in Arabic, the user menu has a link, named "English", to switch to English.
     * AR: داخل اللوحة بالعربي، قائمة اليوزر بيها رابط اسمه "English" للتحويل للإنكليزي.
     */
    public function test_arabic_panel_has_a_link_to_switch_to_english(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->withSession(['locale' => 'ar'])
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('locale.switch', 'en'), false)
            ->assertSee('English');
    }
}
