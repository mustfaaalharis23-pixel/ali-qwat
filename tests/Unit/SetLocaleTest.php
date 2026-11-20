<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    /**
     * EN: When no language has been chosen, Arabic is used.
     * AR: إذا ما انختارت لغة، يستخدم العربي.
     */
    public function test_arabic_is_used_when_no_language_is_chosen(): void
    {
        $this->handleRequestWithSession([]);

        $this->assertSame('ar', App::getLocale());
    }

    /**
     * EN: The language saved in the session is used for the request.
     * AR: اللغة المحفوظة بالـ session تنستخدم بالطلب.
     */
    public function test_language_saved_in_the_session_is_used(): void
    {
        $this->handleRequestWithSession(['locale' => 'zh_CN']);

        $this->assertSame('zh_CN', App::getLocale());
    }

    /**
     * EN: An unsupported language in the session is ignored, and Arabic is used.
     * AR: لغة مو مدعومة بالـ session تنهمل، ويستخدم العربي.
     */
    public function test_unsupported_language_in_the_session_is_ignored(): void
    {
        $this->handleRequestWithSession(['locale' => 'fr']);

        $this->assertSame('ar', App::getLocale());
    }

    /**
     * EN: For a signed-in user, the language saved on their account is used, not the one in the session.
     * AR: لليوزر الداخل، تنستخدم اللغة المحفوظة على حسابه، مو اللي بالـ session.
     */
    public function test_signed_in_users_saved_language_is_used_instead_of_the_session(): void
    {
        $user = new User;
        $user->locale = 'zh_CN';

        $this->handleRequestWithSession(['locale' => 'en'], $user);

        $this->assertSame('zh_CN', App::getLocale());
    }

    /**
     * EN: A signed-in user who never chose a language gets Arabic, whatever language is in the session.
     * AR: اليوزر الداخل اللي عمره ما اختار لغة ياخذ عربي، مهما جانت اللغة اللي بالـ session.
     */
    public function test_signed_in_user_without_a_saved_language_gets_arabic(): void
    {
        $this->handleRequestWithSession(['locale' => 'en'], new User);

        $this->assertSame('ar', App::getLocale());
    }

    /**
     * EN: Passes a request with the given session values, and the given signed-in user if any, through the middleware.
     * AR: يمرر طلب بيه قيم الـ session المعطاة، واليوزر الداخل إذا أكو، على الـ middleware.
     *
     * @param  array<string, string>  $session
     */
    private function handleRequestWithSession(array $session, ?User $user = null): void
    {
        $store = $this->app['session']->driver();
        $store->put($session);

        $request = Request::create('/');
        $request->setLaravelSession($store);
        $request->setUserResolver(fn (): ?User => $user);

        (new SetLocale)->handle($request, fn (): Response => new Response);
    }
}
