<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchLocaleController extends Controller
{
    /**
     * Save the chosen language in the session and go back to the previous page.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('app.locales')), 404);

        $request->session()->put('locale', $locale);

        $previousPage = url()->previous();

        if (parse_url($previousPage, PHP_URL_HOST) !== $request->getHost()) {
            return redirect('/');
        }

        return redirect($previousPage);
    }
}
