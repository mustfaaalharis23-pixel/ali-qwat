<?php

use App\Http\Controllers\PrintInvoiceController;
use App\Http\Controllers\SwitchLocaleController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/locale/{locale}', SwitchLocaleController::class)->name('locale.switch');

/**
 * The printable invoice of one order, in the size it is printed on: a4 or pos.
 */
Route::get('/invoices/{order}/print/{format}', PrintInvoiceController::class)
    ->defaults('format', 'a4')
    ->middleware(['auth', SetLocale::class])
    ->whereIn('format', ['a4', 'pos'])
    ->name('invoices.print');
