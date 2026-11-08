<?php

use App\Http\Controllers\Admin\Reports\ProfitAndLossController;
use App\Http\Controllers\Admin\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->name('reports.')->group(function (): void {
    Route::get('profit-and-loss', [ProfitAndLossController::class, 'show'])->name('profit-and-loss');
    Route::get('profit-and-loss/excel', [ProfitAndLossController::class, 'excel'])->name('profit-and-loss.excel');
    Route::get('purchases/suppliers/{supplier}/items', [ReportController::class, 'supplierItems'])->whereNumber('supplier')->name('purchases.supplier-items');
    Route::get('{report}', [ReportController::class, 'show'])->name('show');
    Route::get('{report}/excel', [ReportController::class, 'excel'])->name('excel');
});
