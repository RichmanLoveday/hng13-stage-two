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

        if (! $res['success']) {
            if (isset($res['api']) && in_array($res['api'], ['countries', 'exchange'])) {
                return response()->json([
                    'error' => 'External data source unavailable',
                    'details' => "Could not fetch data from {$res['api']}"
                ], 503);
            }

            return response()->json(['error' => $res['message'] ?? 'Refresh failed'], 500);
        }

        return response()->json(['message' => $res['message'], 'last_refreshed_at' => $res['last_refreshed_at']]);
    }

    public function allCountries(Request $request)
    {
        $query = CountryCurrency::query();

        if ($request->filled('region')) {
            $query->where('region', $request->get('region'));
        }

        if ($request->filled('currency')) {
            $query->where('currency_code', $request->get('currency'));
        }

        // sorting
        if ($request->get('sort') === 'gdp_desc') {
            $query->orderByDesc('estimated_gdp');
        } elseif ($request->get('sort') === 'gdp_asc') {
            $query->orderBy('estimated_gdp');
        }

        $countries = $query->get();

        return response()->json($countries);
    }

    public function show($name)
    {
        $country = CountryCurrency::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if (! $country) {
            return response()->json(['error' => 'Country not found'], 404);
        }
        return response()->json($country);
    }

    public function destroy($name)
    {
        $country = CountryCurrency::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if (! $country) {
            return response()->json(['error' => 'Country not found'], 404);
        }
        $country->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function status()
    {
        $total = CountryCurrency::count();
        $last = CountryCurrency::orderByDesc('last_refreshed_at')->value('last_refreshed_at');

        return response()->json([
            'total_countries' => $total,
            'last_refreshed_at' => $last ? (new \Carbon\Carbon($last))->toIso8601String() : null,
        ]);
    }

    public function image()
    {
        $path = storage_path('app/public/cache/summary.png');
        if (! file_exists($path)) {
            return response()->json(['error' => 'Summary image not found'], 404);
        }
        return response()->file($path, ['Content-Type' => 'image/png']);
    }
}
