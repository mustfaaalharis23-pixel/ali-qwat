<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    /**
     * EN: Switching to Arabic saves it in the session and goes back to the previous page.
     * AR: التحويل للعربي يحفظه بالـ session ويرجع للصفحة السابقة.
     */
    public function test_switching_to_arabic_saves_it_and_goes_back(): void
    {
        $this->from('/admin')
            ->get(route('locale.switch', 'ar'))
            ->assertRedirect('/admin')
            ->assertSessionHas('locale', 'ar');
    }

    /**
     * EN: Switching back to English saves it in the session.
     * AR: الرجوع للإنكليزي يحفظه بالـ session.
     */
    public function test_switching_back_to_english_saves_it(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->from('/admin')
            ->get(route('locale.switch', 'en'))
            ->assertRedirect('/admin')
            ->assertSessionHas('locale', 'en');
    }

    /**
     * EN: Going back keeps the query string of the previous page, such as the table page number.
     * AR: الرجوع يحافظ على الـ query string مال الصفحة السابقة، مثل رقم صفحة الجدول.
     */
    public function test_going_back_keeps_the_query_string(): void
    {
        $this->from('/admin/users?page=2')
            ->get(route('locale.switch', 'ar'))
            ->assertRedirect('/admin/users?page=2');
    }

    /**
     * EN: Switching to an unsupported language is not found, and nothing is saved.
     * AR: التحويل للغة مو مدعومة يرجع 404، وما ينحفظ شي.
     */
    public function test_switching_to_an_unsupported_language_is_not_found(): void
    {
        $this->get(route('locale.switch', 'fr'))
            ->assertNotFound()
            ->assertSessionMissing('locale');
    }

    /**
     * EN: Previous pages on other sites, including ones that start with our host name.
     * AR: صفحات سابقة من مواقع ثانية، حتى اللي تبدي باسم موقعنا.
     *
     * @return array<string, array{string}>
     */
    public static function externalPages(): array
    {
        return [
            'another site' => ['https://evil.example/page'],
            'a site that starts with our host name' => ['http://localhost.evil.example/page'],
        ];
    }

    /**
     * EN: If the previous page is on another site, switching goes to our home page instead, and the language is still saved.
     * AR: إذا الصفحة السابقة من موقع ثاني، التحويل يروح للصفحة الرئيسية مالتنا بدالها، واللغة تنحفظ عادي.
     */
    #[DataProvider('externalPages')]
    public function test_switching_never_goes_back_to_another_site(string $previousPage): void
    {
        $this->from($previousPage)
            ->get(route('locale.switch', 'ar'))
            ->assertRedirect(url('/'))
            ->assertSessionHas('locale', 'ar');
    }
}
