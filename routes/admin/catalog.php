<?php

use App\Http\Controllers\Admin\Catalog\BatchController;
use App\Http\Controllers\Admin\Catalog\ColorController;
use App\Http\Controllers\Admin\Catalog\ItemCategoryController;
use App\Http\Controllers\Admin\Catalog\ItemController;
use App\Http\Controllers\Admin\Catalog\ItemExcelController;
use App\Http\Controllers\Admin\Catalog\LensController;
use App\Http\Controllers\Admin\Catalog\LensPowerController;
use Illuminate\Support\Facades\Route;

Route::post('colors/{color}/restore', [ColorController::class, 'restore'])->name('colors.restore');
Route::delete('colors/{color}/force', [ColorController::class, 'forceDelete'])->name('colors.force-delete');
Route::apiResource('colors', ColorController::class)->except('show');

Route::post('lens-powers/{lensPower}/restore', [LensPowerController::class, 'restore'])->name('lens-powers.restore');
Route::delete('lens-powers/{lensPower}/force', [LensPowerController::class, 'forceDelete'])->name('lens-powers.force-delete');
Route::apiResource('lens-powers', LensPowerController::class)->except('show');

Route::post('item-categories/{category}/restore', [ItemCategoryController::class, 'restore'])->whereNumber('category')->name('item-categories.restore');
Route::delete('item-categories/{category}/force', [ItemCategoryController::class, 'forceDelete'])->whereNumber('category')->name('item-categories.force-delete');
Route::apiResource('item-categories', ItemCategoryController::class)->except('show')->parameters(['item-categories' => 'category']);

Route::get('items/excel/template', [ItemExcelController::class, 'template'])->name('items.excel.template');
Route::post('items/excel', [ItemExcelController::class, 'preview'])->name('items.excel.preview');
Route::post('items/excel/import', [ItemExcelController::class, 'store'])->name('items.excel.store');

Route::prefix('items')->name('items.')->controller(ItemController::class)->group(function (): void {
    Route::get('metrics', 'listMetrics')->name('list-metrics');
    Route::post('bulk', 'bulk')->name('bulk');
    Route::post('supplier', 'bulkSupplier')->name('bulk-supplier');
    Route::get('{item}', 'show')->whereNumber('item')->name('show');
    Route::get('{item}/metrics', 'metrics')->whereNumber('item')->name('metrics');
    Route::post('{item}/restore', 'restore')->whereNumber('item')->name('restore');
    Route::delete('{item}/force', 'forceDelete')->whereNumber('item')->name('force-delete');
    Route::put('{item}/sale-price', 'salePrice')->name('sale-price');
    Route::post('{item}/activate', 'activate')->name('activate');
    Route::post('{item}/recalculate-stock', 'recalculateStock')->name('recalculate-stock');
    Route::put('{item}/supplier', 'supplier')->name('supplier');
});
Route::apiResource('items', ItemController::class)->except('show')->withTrashed(['update']);

Route::get('batches', [BatchController::class, 'index'])->name('batches.index');

Route::get('lenses/{key}', [LensController::class, 'show'])->name('lenses.show');
