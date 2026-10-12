<?php

use App\Enums\AppPermission;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\PurchaseInvoiceController;
use Illuminate\Support\Facades\Route;

/**
 * The routes of the sales application. Everything is under /api/v1 so the application keeps working
 * when a newer version of the API comes out.
 */
Route::prefix('v1')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('items', [ItemController::class, 'index'])
            ->middleware('app.permission:'.AppPermission::Checkout->value);

        Route::get('packages', [PackageController::class, 'index'])
            ->middleware('app.permission:'.AppPermission::Checkout->value);

        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('app.permission:'.AppPermission::Orders->value.','.AppPermission::Checkout->value);

        Route::get('orders', [OrderController::class, 'index'])
            ->middleware('app.permission:'.AppPermission::Orders->value);

        Route::get('orders/{order}', [OrderController::class, 'show'])
            ->middleware('app.permission:'.AppPermission::Orders->value);

        Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])
            ->middleware('app.permission:'.AppPermission::Orders->value);

        Route::post('orders', [OrderController::class, 'store'])
            ->middleware('app.permission:'.AppPermission::Checkout->value);

        Route::get('purchase-invoices', [PurchaseInvoiceController::class, 'index'])
            ->middleware('app.permission:'.AppPermission::Receiving->value);

        Route::get('purchase-invoices/{invoice}', [PurchaseInvoiceController::class, 'show'])
            ->middleware('app.permission:'.AppPermission::Receiving->value);

        Route::post('purchase-invoices/receive', [PurchaseInvoiceController::class, 'receive'])
            ->middleware('app.permission:'.AppPermission::Receiving->value);
    });
});
