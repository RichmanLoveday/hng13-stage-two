<?php

namespace App\Http\Controllers;

use App\Services\CountryCurrencyService;
use App\Models\CountryCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CountryCurrencyController extends Controller
{
    public function refresh(Request $request, CountryCurrencyService $service)
    {
        $res = $service->refresh();

        if (!$res['success']) {
            if (isset($res['api']) && in_array($res['api'], ['countries', 'exchange'])) {
                return response()->json([
                    'error' => 'External data source unavailable',
                    'details' => "Could not fetch data from {$res['api']}"
                ], $res['code']);
            }

            //? check for validation
            if (isset($res['type']) && $res['type'] === 'validation') {
                return response()->json([
                    'error' => $res['message'],
                    'details' => $res['details'],
                ], $res['code']);
            }

            return response()->json([
                'error' => $res['message'] ?? 'Refresh failed'
            ], $res['code']);
        }


        return response()->json([
            'message' => $res['message'],
            'last_refreshed_at' => $res['last_refreshed_at']
        ], $res['code']);
    }

    public function allCountries(Request $request, CountryCurrencyService $service)
    {
        $res = $service->getAllCountries($request->all());

        if ($res['success']) {
            return response()->json(
                $res['countries'],
                $res['code']
            );
        }

        return response()->json([
            'error' => $res['message']
        ], $res['code']);
    }


    public function show(string $name, CountryCurrencyService $service)
    {
        $res = $service->findSingleCountry($name);
        if (!$res["success"]) {
            return response()->json(
                ["error" => $res['message']],
                $res['code']
            );
        }

        return response()->json(
            $res['country'],
            $res['code']
        );
    }

    public function destroy(string $name, CountryCurrencyService $service)
    {
        $res = $service->deleteCountry($name);
        if (!$res["success"]) {
            return response()->json(
                ["error" => $res['message']],
                $res['code']
            );
        }

        return response()->json(
            ["message" => $res['message']],
            $res['code']
        );
    }


    public function status(CountryCurrencyService $service)
    {
        $res = $service->status();

        if ($res["success"]) {
            return response()->json(
                [
                    "total_countries" => $res['total_countries'],
                    "last_refreshed_at" => $res['last_refreshed_at'],
                ],
                $res['code']
            );
        }

        return response()->json(
            ["error" => $res['message']],
            $res['code']
        );
    }


    public function image(CountryCurrencyService $service)
    {
        $path = storage_path($service->imagePath);
        // dd($path);
        if (! file_exists($path)) {
            return response()->json(['error' => 'Summary image not found'], 404);
        }
        return response()->file($path, ['Content-Type' => 'image/png']);
    }
}