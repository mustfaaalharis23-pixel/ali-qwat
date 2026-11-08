<?php

use App\Http\Controllers\Admin\System\AuditController;
use App\Http\Controllers\Admin\System\GeneralDescriptionController;
use App\Http\Controllers\Admin\System\GeneralInvoiceController;
use App\Http\Controllers\Admin\System\SpecificDescriptionController;
use App\Http\Controllers\Admin\System\SystemOptionController;
use App\Http\Controllers\Admin\System\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Accounting (general invoices and their descriptions) and system administration (users and audits).
 */
Route::get('system-options/general-descriptions', [SystemOptionController::class, 'generalDescriptions'])->name('system-options.general-descriptions');
Route::get('system-options/specific-descriptions', [SystemOptionController::class, 'specificDescriptions'])->name('system-options.specific-descriptions');

Route::get('general-invoices/expenses-chart', [GeneralInvoiceController::class, 'expensesChart'])->name('general-invoices.expenses-chart');
Route::post('general-invoices/images', [GeneralInvoiceController::class, 'uploadImage'])->name('general-invoices.images');

Route::get('users/permission-groups', [UserController::class, 'permissionGroups'])->name('users.permission-groups');
Route::get('users/{user}/permissions', [UserController::class, 'permissions'])->whereNumber('user')->name('users.permissions');

Route::get('audits/filters', [AuditController::class, 'filters'])->name('audits.filters');
Route::get('audits', [AuditController::class, 'index'])->name('audits.index');
Route::get('audits/{audit}', [AuditController::class, 'show'])->whereNumber('audit')->name('audits.show');

foreach ([
    'general-invoices' => GeneralInvoiceController::class,
    'general-descriptions' => GeneralDescriptionController::class,
    'specific-descriptions' => SpecificDescriptionController::class,
    'users' => UserController::class,
] as $path => $controller) {
    $parameter = 'record';

    Route::post("{$path}/{{$parameter}}/restore", [$controller, 'restore'])->whereNumber($parameter)->name("{$path}.restore");
    Route::delete("{$path}/{{$parameter}}/force", [$controller, 'forceDelete'])->whereNumber($parameter)->name("{$path}.force-delete");
    Route::apiResource($path, $controller)->parameters([$path => $parameter])->where([$parameter => '[0-9]+']);
}
