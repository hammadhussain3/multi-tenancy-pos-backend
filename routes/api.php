<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DayClosingController;
use App\Http\Controllers\Api\PosCheckoutController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pos')->group(function () {
    // Products & Inventory
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/adjust-stock', [ProductController::class, 'adjustStock']);

    // Customers & Khata Ledger
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}/ledger', [CustomerController::class, 'ledger']);
    Route::post('/customers/{id}/payment', [CustomerController::class, 'recordPayment']);

    // POS Checkout & Invoices
    Route::post('/checkout', [PosCheckoutController::class, 'checkout']);
    Route::get('/invoices', [PosCheckoutController::class, 'invoices']);

    // Daily Register Closing & Cash Report
    Route::get('/day-closing', [DayClosingController::class, 'summary']);
});
