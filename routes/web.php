<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});




Route::get('/deploy/migrate-fresh', function () {
    // OPTIONAL: Protect route with a secret key for safety
    $secret = request()->query('secret');
    if ($secret !== env('DEPLOY_SECRET')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    try {
        // Run fresh migration and seed
        Artisan::call('migrate:fresh', [
            '--seed' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Database migrated fresh and seeded successfully.',
            'output' => Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
});