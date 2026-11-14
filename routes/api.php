<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DayClosingController;
use App\Http\Controllers\Api\PosCheckoutController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pos')->group(function () {
    // 1. Public Endpoints (Pre-Login & Store Selection)
    Route::get('/tenants', [TenantController::class, 'index']);

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/pin-login', [AuthController::class, 'pinLogin']);
    });

    // 2. Protected POS Endpoints (Strictly require active Laravel Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        // Authenticated Cashier / Shift Profile
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // Store & Multi-Tenant Context
        Route::get('/tenant/current', [TenantController::class, 'current']);

        // Products & Inventory Management
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/{id}', [ProductController::class, 'show']);
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']);
            Route::post('/{id}/adjust-stock', [ProductController::class, 'adjustStock']);
        });

        // Customers & Khata Ledger
        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'index']);
            Route::get('/{id}/ledger', [CustomerController::class, 'ledger']);
            Route::post('/{id}/payment', [CustomerController::class, 'recordPayment']);
        });

        // POS Checkout & Invoices
        Route::post('/checkout', [PosCheckoutController::class, 'checkout']);
        Route::get('/invoices', [PosCheckoutController::class, 'invoices']);

        // Daily Register Closing & Shift Reconciliation
        Route::get('/day-closing', [DayClosingController::class, 'summary']);
    });
});
