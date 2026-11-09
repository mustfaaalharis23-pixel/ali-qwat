<?php

use App\Http\Controllers\Admin\Sales\AbilitiesController;
use App\Http\Controllers\Admin\Sales\CustomerController;
use App\Http\Controllers\Admin\Sales\CustomerPaymentController;
use App\Http\Controllers\Admin\Sales\InvoicePrintingController;
use App\Http\Controllers\Admin\Sales\OrderController;
use Illuminate\Support\Facades\Route;

/**
 * The sales screens: the orders and their checkout cart, the customers, the customer payments, and the invoice printing.
 */
Route::get('sales/abilities', AbilitiesController::class)->name('sales.abilities');

Route::post('orders/bulk', [OrderController::class, 'bulk'])->name('orders.bulk');
Route::post('orders/checkout', [OrderController::class, 'checkout'])->name('orders.checkout');
Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
Route::post('orders/{order}/restore', [OrderController::class, 'restore'])->name('orders.restore');
Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
Route::apiResource('orders', OrderController::class)->except('show');

Route::post('customers/bulk', [CustomerController::class, 'bulk'])->name('customers.bulk');
Route::get('customers/{customer}/metrics/{key}', [CustomerController::class, 'metric'])->name('customers.metric');
Route::get('customers/{customer}/payments', [CustomerController::class, 'payments'])->name('customers.payments');
Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
Route::delete('customers/{customer}/force', [CustomerController::class, 'forceDelete'])->name('customers.force-delete');
Route::post('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update-with-files');
Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
Route::apiResource('customers', CustomerController::class)->except('show');

Route::prefix('customer-payments')->name('customer-payments.')->controller(CustomerPaymentController::class)->group(function (): void {
    Route::get('overview', 'overview')->name('overview');
    Route::get('form', 'form')->name('form');
    Route::get('statement', 'statement')->name('statement');
    Route::get('reminders', 'reminders')->name('reminders');
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('{payment}', 'show')->whereNumber('payment')->name('show');
    Route::delete('{payment}', 'destroy')->whereNumber('payment')->name('destroy');
});

Route::get('invoice-printing', InvoicePrintingController::class)->name('invoice-printing');
