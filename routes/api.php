<?php

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

Route::controller(GoogleAuthController::class)->group(function () {
    Route::get('auth/google/redirect', 'redirect');
    Route::get('auth/google/callback', 'callback');
});


Route::middleware('auth:api')->group(function () {
    Route::controller(WalletController::class)->group(function () {
        Route::get('/wallet/balance', 'balance');
        Route::post('/wallet/deposit', 'deposit');
        Route::post('/wallet/transfer', 'transfer');
        Route::get('/wallet/deposit/{reference}/status', 'depositStatus');
        Route::get('/wallat/transactions', 'transactionHistory');
    });
});

// Paystack webhook route
Route::post('/wallet/paystack/webhook', [WalletController::class, 'depositCallback'])
    ->name('wallet.deposit.callback');

// Route::post('/wallet/paystack/webhook', function (Request $request) {
//     Log::info('Webhook hit', ['body' => $request->all()]);
//     return response()->json(['status' => true]);
// });