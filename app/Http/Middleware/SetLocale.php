<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * A signed-in user gets the language saved on their account (Arabic when they never chose one), whatever the session holds.
     * A guest (the login page) gets the language saved in the session when it is supported; otherwise the default language.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            App::setLocale($user->preferredLocale());

            return $next($request);
        }

        $locale = $request->session()->get('locale');

        if (is_string($locale) && array_key_exists($locale, config('app.locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
