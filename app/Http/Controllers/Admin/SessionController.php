<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The sign-in of the admin application on the web session: who is signed in, signing in and out, and the language.
 * It signs in the same users as the panel: an active user that is not deleted.
 */
class SessionController extends Controller
{
    /**
     * The signed-in user (or null), the language, and the languages to choose from.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    /**
     * Signs in with an email and a password. A wrong pair and an inactive user get the same answer, so the form
     * does not tell which emails exist.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! $user->is_active || ! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return response()->json($this->payload(Auth::user()));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json($this->payload(null));
    }

    /**
     * Saves the chosen language in the session, the same place the panel keeps it.
     */
    public function locale(Request $request): JsonResponse
    {
        $locale = $request->validate(['locale' => ['required', Rule::in(array_keys(config('app.locales')))]])['locale'];

        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        return response()->json($this->payload($request->user()));
    }

    /**
     * @return array{user: ?array{id: int, name: string, email: string, is_super: bool, is_cashier: bool}, locale: string, locales: array<string, string>}
     */
    private function payload(?User $user): array
    {
        return [
            'user' => $user === null ? null : [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super' => (bool) $user->is_super,
                'is_cashier' => (bool) $user->is_cashier,
            ],
            'locale' => App::getLocale(),
            'locales' => config('app.locales'),
        ];
    }
}
