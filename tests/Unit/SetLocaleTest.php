<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
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
     * EN: Passes a request with the given session values through the middleware.
     * AR: يمرر طلب بيه قيم الـ session المعطاة على الـ middleware.
     *
     * @param  array<string, string>  $session
     */
    private function handleRequestWithSession(array $session): void
    {
        $store = $this->app['session']->driver();
        $store->put($session);

        $request = Request::create('/');
        $request->setLaravelSession($store);

        (new SetLocale)->handle($request, fn (): Response => new Response);
    }
}
