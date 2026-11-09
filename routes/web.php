<?php

use App\Http\Controllers\DownloadPurchaseLabelController;
use App\Http\Controllers\PrintInvoiceController;
use App\Http\Controllers\PrintPaymentReceiptController;
use App\Http\Controllers\PrintPurchaseInvoiceController;
use App\Http\Controllers\SwitchLocaleController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
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
 * The receipt of one customer payment, for the small thermal printer.
 */
Route::get('/customer-payments/{payment}/receipt', PrintPaymentReceiptController::class)
    ->middleware(['auth', SetLocale::class])
    ->name('customer-payments.receipt');

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

/**
 * The admin application: one page, and React draws every screen in the browser from /admin-api.
 */
Route::view('/app/{path?}', 'admin')
    ->where('path', '.*')
    ->name('app');

/**
 * The old admin panel's addresses lead to the same screen of the admin application, for the links and bookmarks
 * people still have: /admin to /app, and /admin/orders/5 to /app/orders/5 (the sign-in page included), with their query string.
 */
Route::get('/admin/{path?}', function (Request $request, ?string $path = null) {
    $query = $request->getQueryString();

    return redirect()->to('/app'.($path === null ? '' : "/{$path}").($query === null ? '' : "?{$query}"), 301);
})
    ->where('path', '.*')
    ->name('admin.redirect');
