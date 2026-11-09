<?php

use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\OptionController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

/**
 * The JSON API of the admin application (the React single page application at /app). It runs on the web session,
 * so the browser signs in once with a cookie, and every write is protected from forgery by the XSRF-TOKEN cookie.
 * Every module adds its own routes in routes/admin/{module}.php, which are loaded here behind the sign-in.
 */
Route::get('session', [SessionController::class, 'show'])->name('session');
Route::post('login', [SessionController::class, 'login'])->name('login');
Route::post('locale', [SessionController::class, 'locale'])->name('locale');
Route::get('translations/{locale}', TranslationController::class)->name('translations');

Route::middleware(['auth', EnsureActiveUser::class])->group(function (): void {
    Route::post('logout', [SessionController::class, 'logout'])->name('logout');
    Route::get('navigation', NavigationController::class)->name('navigation');
    Route::get('options/{type}', OptionController::class)->name('options');

    foreach (glob(__DIR__.'/admin/*.php') ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});
