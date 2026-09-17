<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /**
     * Use the language the app asks for in the Accept-Language header when it is supported; otherwise keep the default language.
     * The API has no session, so the header is the only place the language comes from.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(array_keys(config('app.locales')));

        if (is_string($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
