<?php

use App\Http\Controllers\CountryCurrencyController;
use Illuminate\Http\Request;
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