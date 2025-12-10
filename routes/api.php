<?php

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CountryCurrencyController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::controller(CountryCurrencyController::class)->group(function () {
    Route::post('countries/refresh', 'refresh');
    Route::get('countries', 'allCountries');
    Route::get('countries/image', 'image');
    Route::get('countries/{name}', 'show');
    Route::get('status', 'status');
    Route::delete('countries/{name}', 'destroy');
});

// Google Auth Routes
Route::controller(GoogleAuthController::class)->group(function () {
    Route::get('auth/google/redirect', 'redirect');
    Route::get('auth/google/callback', 'callback');
});

// API Key Routes
Route::middleware('auth:api')->group(function () {
    Route::controller(ApiKeyController::class)->group(function () {
        Route::post('/keys/create', 'create');
        Route::post('/keys/rollover', 'rollover');
    });
});


// Wallet routes
Route::controller(WalletController::class)->group(function () {
    Route::get('/wallet/balance', 'balance')->middleware('auth_or_api_key:read');
    Route::post('/wallet/deposit', 'deposit')->middleware('auth_or_api_key:deposit');
    Route::post('/wallet/transfer', 'transfer')->middleware('auth_or_api_key:transfer');
    Route::get('/wallet/deposit/{reference}/status', 'depositStatus')->middleware('auth_or_api_key:read');
    Route::get('/wallat/transactions', 'transactionHistory')->middleware('auth_or_api_key:read');
});


// Paystack webhook route
Route::post('/wallet/paystack/webhook', [WalletController::class, 'depositCallback'])
    ->name('wallet.deposit.callback');