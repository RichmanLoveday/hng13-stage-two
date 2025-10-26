<?php

namespace App\Services;

use App\Models\CountryCurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CountryCurrencyService
{
    private string $countriesApi;
    private string $exchangeApi;
    private int $timeout;

    public function __construct()
    {
        //  dd(config('services.exchange_rate'));
        $this->countriesApi = config('services.country'); // e.g. https://restcountries.com/v2/all?fields=name,capital,region,population,flag,currencies
        $this->exchangeApi = config('services.exchange_rate'); // e.g. https://open.er-api.com/v6/latest/USD
        $this->timeout = config('services.api_timeout', 15);
    }

    /**
     * Refresh countries — fetch external data and update DB.
     * Returns array: [ 'success' => bool, 'message' => string ]
     */
    public function refresh(): array
    {
        // 1) fetch countries
        $countriesResponse = Http::timeout($this->timeout)->get($this->countriesApi);
        if (! $countriesResponse->successful()) {
            Log::error('Countries API failed: ' . $countriesResponse->status());
            return ['success' => false, 'message' => 'Could not fetch countries', 'api' => 'countries'];
        }

        $exchangeResponse = Http::timeout($this->timeout)->get($this->exchangeApi);
        if (! $exchangeResponse->successful()) {
            Log::error('Exchange API failed: ' . $exchangeResponse->status());
            return ['success' => false, 'message' => 'Could not fetch exchange rates', 'api' => 'exchange'];
        }

        $countries = $countriesResponse->json(); // array
        $exchange = $exchangeResponse->json(); // array, keys: base_code?, rates

        // Keep rates map for O(1) lookup.
        $rates = $exchange['rates'] ?? [];
        $baseCode = $exchange['base_code'] ?? ($exchange['base'] ?? 'USD');

        // Begin DB transaction — if anything fails we will rollback
        DB::beginTransaction();
        try {
            $now = Carbon::now();

            foreach ($countries as $item) {
                // Validate minimal required fields before DB operations
                $name = data_get($item, 'name');
                $population = data_get($item, 'population');
                $flag = data_get($item, 'flag');
                $capital = data_get($item, 'capital');
                $region = data_get($item, 'region');

                $currencyCode = null;
                $currencies = data_get($item, 'currencies');

                if (is_array($currencies) && count($currencies) > 0) {
                    $first = $currencies[0];
                    if (is_array($first)) {
                        $currencyCode = $first['code'] ?? null;
                    } elseif (is_object($first)) {
                        $currencyCode = $first->code ?? null;
                    }
                }

                // Prepare exchange_rate and estimated_gdp according to spec
                $exchangeRate = null;
                $estimatedGdp = null;

                if ($currencyCode !== null) {
                    // Lookup in rates map — currency might be not present
                    $exchangeRate = $rates[$currencyCode] ?? null;

                    if ($exchangeRate !== null && $population !== null && is_numeric($population) && $exchangeRate > 0) {
                        // random multiplier 1000-2000 per refresh
                        $mult = random_int(1000, 2000);
                        $estimatedGdp = ($population * $mult) / $exchangeRate;
                    } elseif ($exchangeRate === null) {
                        // per spec: set estimated_gdp to null if exchange missing
                        $estimatedGdp = null;
                    }
                } else {
                    // currencies empty — still store record with nulls
                    $currencyCode = null;
                    $exchangeRate = null;
                    $estimatedGdp = 0; // per spec
                }

                // Upsert by name (case-insensitive). Use lower-case name for matching.
                $existing = CountryCurrency::whereRaw('LOWER(name) = ?', [mb_strtolower($name ?? '')])->first();

                $payload = [
                    'name' => $name,
                    'capital' => $capital,
                    'region' => $region,
                    'population' => $population ?? 0,
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'estimated_gdp' => $estimatedGdp,
                    'flag_url' => $flag,
                    'last_refreshed_at' => $now,
                ];

                if ($existing) {
                    $existing->update($payload);
                } else {
                    CountryCurrency::create($payload);
                }
            }

            // commit only after loop completes successfully
            DB::commit();

            // Generate summary image
            $this->generateSummaryImage($now);

            return ['success' => true, 'message' => 'Refreshed successfully', 'last_refreshed_at' => $now->toIso8601String()];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refresh failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Internal error during refresh'];
        }
    }

    private function generateSummaryImage(\Carbon\Carbon $timestamp): void
    {
        // Get totals and top 5
        $total = CountryCurrency::count();
        $top5 = CountryCurrency::whereNotNull('estimated_gdp')
            ->orderByDesc('estimated_gdp')
            ->limit(5)
            ->get(['name', 'estimated_gdp']);

        // Prepare text lines
        $lines = [];
        $lines[] = "Total countries: {$total}";
        $lines[] = "Last refresh: " . $timestamp->toIso8601String();
        $lines[] = "Top 5 by estimated GDP:";
        foreach ($top5 as $i => $c) {
            $lines[] = ($i + 1) . ". {$c->name} — " . number_format($c->estimated_gdp, 2);
        }

        // Create a simple PNG using GD and store to storage/app/public/cache/summary.png
        $width = 1000;
        $height = 600;

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $fontSize = 5; // built-in font
        $y = 20;
        imagestring($img, $fontSize, 10, $y, 'Country Currency Summary', $black);
        $y += 30;

        foreach ($lines as $line) {
            imagestring($img, 4, 10, $y, $line, $black);
            $y += 22;
        }

        // Ensure directory exists
        Storage::disk('public')->makeDirectory('cache');
        $path = storage_path('app/public/cache/summary.png');
        imagepng($img, $path);
        imagedestroy($img);
    }
}
