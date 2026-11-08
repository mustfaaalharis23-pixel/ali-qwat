<?php

use App\Http\Controllers\Admin\Purchases\ContainerController;
use App\Http\Controllers\Admin\Purchases\InvoiceCategoryController;
use App\Http\Controllers\Admin\Purchases\PayoffController;
use App\Http\Controllers\Admin\Purchases\PurchaseInvoiceController;
use App\Http\Controllers\Admin\Purchases\SupplierController;
use Illuminate\Support\Facades\Route;

/**
 * The purchases area of the admin application: purchase invoices with their payoffs, suppliers, containers, and invoice categories.
 */
Route::prefix('purchase-invoices')->name('purchase-invoices.')->controller(PurchaseInvoiceController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('uploads', 'upload')->name('upload');
    Route::get('metrics/{key}', 'metric')->name('metric');
    Route::get('chart', 'chart')->name('chart');
    Route::get('line-options', 'lineOptions')->name('line-options');
    Route::get('lens-power-options', 'lensPowerOptions')->name('lens-power-options');
    Route::get('line-item/{description}', 'lineItem')->whereNumber('description')->name('line-item');
    Route::get('supplier-debt/{supplier}', 'supplierDebt')->whereNumber('supplier')->name('supplier-debt');
    Route::get('{invoice}', 'show')->whereNumber('invoice')->name('show');
    Route::put('{invoice}', 'update')->whereNumber('invoice')->name('update');
    Route::delete('{invoice}', 'destroy')->whereNumber('invoice')->name('destroy');
    Route::post('{invoice}/restore', 'restore')->whereNumber('invoice')->name('restore');
    Route::delete('{invoice}/force', 'forceDelete')->whereNumber('invoice')->name('force-delete');
    Route::post('{invoice}/advance-stage', 'advanceStage')->whereNumber('invoice')->name('advance-stage');
    Route::post('{invoice}/confirm', 'confirm')->whereNumber('invoice')->name('confirm');
    Route::post('{invoice}/revert-confirmation', 'revertConfirmation')->whereNumber('invoice')->name('revert-confirmation');
    Route::get('{invoice}/payoffs', [PayoffController::class, 'index'])->whereNumber('invoice')->name('payoffs.index');
    Route::post('{invoice}/payoffs', [PayoffController::class, 'store'])->whereNumber('invoice')->name('payoffs.store');
});

Route::prefix('payoffs')->name('payoffs.')->controller(PayoffController::class)->group(function (): void {
    Route::put('{payoff}', 'update')->whereNumber('payoff')->name('update');
    Route::delete('{payoff}', 'destroy')->whereNumber('payoff')->name('destroy');
    Route::post('{payoff}/restore', 'restore')->whereNumber('payoff')->name('restore');
    Route::delete('{payoff}/force', 'forceDelete')->whereNumber('payoff')->name('force-delete');
});

Route::prefix('suppliers')->name('suppliers.')->controller(SupplierController::class)->group(function (): void {
    Route::post('uploads', 'upload')->name('upload');
    Route::get('metrics/total_debt', 'totalDebt')->name('total-debt');
    Route::get('chart', 'chart')->name('chart');
    Route::get('{supplier}/metrics/{key}', 'metric')->whereNumber('supplier')->name('metric');
    Route::get('{supplier}/invoices', 'invoices')->whereNumber('supplier')->name('invoices');
    Route::get('{supplier}/items', 'items')->whereNumber('supplier')->name('items');
    Route::get('{supplier}/debt', 'debt')->whereNumber('supplier')->name('debt');
    Route::post('{supplier}/distribute-payoff', 'distributePayoff')->whereNumber('supplier')->name('distribute-payoff');
    Route::post('{supplier}/restore', 'restore')->whereNumber('supplier')->name('restore');
    Route::delete('{supplier}/force', 'forceDelete')->whereNumber('supplier')->name('force-delete');
});
Route::apiResource('suppliers', SupplierController::class)->where(['supplier' => '[0-9]+']);

Route::prefix('containers')->name('containers.')->controller(ContainerController::class)->group(function (): void {
    Route::get('{container}/metrics/{key}', 'metric')->whereNumber('container')->name('metric');
    Route::get('{container}/invoices', 'invoices')->whereNumber('container')->name('invoices');
    Route::get('{container}/invoice-options', 'invoiceOptions')->whereNumber('container')->name('invoice-options');
    Route::post('{container}/invoices', 'addInvoices')->whereNumber('container')->name('add-invoices');
    Route::post('{container}/invoices/take-out', 'takeInvoicesOut')->whereNumber('container')->name('take-invoices-out');
    Route::get('{container}/splittable', 'splittable')->whereNumber('container')->name('splittable');
    Route::get('{container}/splittable/{invoice}', 'splittableLines')->whereNumber(['container', 'invoice'])->name('splittable-lines');
    Route::post('{container}/split', 'split')->whereNumber('container')->name('split');
    Route::post('{container}/restore', 'restore')->whereNumber('container')->name('restore');
    Route::delete('{container}/force', 'forceDelete')->whereNumber('container')->name('force-delete');
});
Route::apiResource('containers', ContainerController::class)->where(['container' => '[0-9]+']);

Route::post('invoice-categories/{category}/restore', [InvoiceCategoryController::class, 'restore'])->whereNumber('category')->name('invoice-categories.restore');
Route::delete('invoice-categories/{category}/force', [InvoiceCategoryController::class, 'forceDelete'])->whereNumber('category')->name('invoice-categories.force-delete');
Route::apiResource('invoice-categories', InvoiceCategoryController::class)->except('show')->parameters(['invoice-categories' => 'category']);
