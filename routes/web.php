<?php

use App\Http\Controllers\DownloadPurchaseLabelController;
use App\Http\Controllers\PrintInvoiceController;
use App\Http\Controllers\PrintPurchaseInvoiceController;
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

/**
 * The checking sheet of one purchase invoice, for the employee who receives and counts the goods.
 */
Route::get('/purchase-invoices/{invoice}/print', PrintPurchaseInvoiceController::class)
    ->middleware(['auth', SetLocale::class])
    ->name('purchase-invoices.print');

/**
 * The carton label of one purchase invoice as a PDF file, for the supplier's factory.
 */
Route::get('/purchase-invoices/{invoice}/label', DownloadPurchaseLabelController::class)
    ->middleware(['auth', SetLocale::class])
    ->name('purchase-invoices.label');
