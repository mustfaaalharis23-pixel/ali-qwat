<?php

use App\Http\Controllers\Admin\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->name('dashboard.')->controller(DashboardController::class)->group(function (): void {
    Route::get('financial', 'financial')->name('financial');
    Route::get('metrics/{key}', 'metric')->name('metric');
    Route::get('cashiers', 'cashiers')->name('cashiers');
    Route::post('cashiers/transfer', 'transfer')->name('transfer');
    Route::get('monitoring', 'monitoring')->name('monitoring');
    Route::get('reminders', 'reminders')->name('reminders');
});
