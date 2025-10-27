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
    public string $imagePath;

    public function __construct()
    {
        // dd(config('services.exchange_rate'));
        $this->countriesApi = config('services.country');
        $this->exchangeApi = config('services.exchange_rate');
        $this->timeout = config('services.api_timeout', 15);
        $this->imagePath = config('services.image_path');
    }


    public function refresh(): array
    {

        try {
            //? fetch countries api
            $countriesResponse = $this->fetchCountries();
            if (!$countriesResponse['success']) {
                return $countriesResponse;
            }



            //? fetch exchange rate api
            $exchangeResponse = $this->fetchExchangeRateApi();
            if (!$exchangeResponse['success']) {
                return $exchangeResponse;
            }


            //? get countries datas and exhange rate data
            $countries = $countriesResponse['data'];
            $exchange = $exchangeResponse['data'];

            // dd($countries);


            // dd($countries);
            //? Keep rates and base code for a lookup
            $rates = $exchange['rates'] ?? [];
            $baseCode = $exchange['base_code'] ?? ($exchange['base'] ?? 'USD');



            //? Begin DB transaction — if anything fails we will rollback
            DB::beginTransaction();

            $now = Carbon::now();
            foreach ($countries as $item) {
                //? Initialize validation details for this record
                $validationDetails = [];

                // dd($item['capital']);
                //? Validate minimal required fields before DB operations
                $name = $item['name'] ?? Null;
                $population = $item['population'] ?? Null;
                $flag = $item['flag'] ?? Null;
                $capital = $item['capital'] ?? Null;
                $region = $item['region'] ?? Null;

                $currencyCode = null;
                $currencies = $item['currencies'] ?? [];

                //? Validation (only name and population are required)
                if (empty($name)) {
                    $validationDetails['name'] = 'name is required';
                }
                if (empty($population)) {
                    $validationDetails['population'] = 'population is required';
                }

                if (!empty($validationDetails)) {
                    continue;
                    // DB::rollBack();
                    // return [
                    //     "success" => false,
                    //     "message" => "Validation failed",
                    //     "type" => "validation",
                    //     "details" => $validationDetails,
                    //     "code" => 400,
                    // ];
                }


                //? check if currencies is an array and is greater than one
                if (is_array($currencies) && count($currencies) > 0) {
                    $first = $currencies[0];
                    if (is_array($first)) {
                        $currencyCode = $first['code'] ?? null;
                    } elseif (is_object($first)) {      //? if it an object
                        $currencyCode = $first->code ?? null;
                    }
                }


                //? Prepare exchange_rate and estimated_gdp according to spec
                $exchangeRate = null;
                $estimatedGdp = null;

                if ($currencyCode !== null) {
                    //? Lookup in rates, and check if currency code exist in rates
                    $exchangeRate = $rates[$currencyCode] ?? null;

                    if ($exchangeRate !== null && $population !== null && is_numeric($population) && $exchangeRate > 0) {
                        //? random multiplier 1000-2000 per refresh
                        $mult = random_int(1000, 2000);
                        $estimatedGdp = ($population * $mult) / $exchangeRate;
                    } elseif ($exchangeRate === null) {
                        //? if exchange rate is null, set estimatedGdp to null
                        $estimatedGdp = null;
                    }
                } else {
                    //? currencies empty — still store record with nulls
                    $currencyCode = null;
                    $exchangeRate = null;
                    $estimatedGdp = 0; // per spec
                }


                //? Upsert by name (case-insensitive). Use lower-case name for matching.
                $existing = CountryCurrency::whereRaw('LOWER(name) = ?', [mb_strtolower($name ?? '')])
                    ->first();

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

                //? check if country exist
                if ($existing) {
                    $existing->update($payload);
                } else {
                    CountryCurrency::create($payload);
                }
            }

            //? commit only after loop completes successfully
            DB::commit();

            //? Generate summary image
            $this->generateSummaryImage($now);

            return [
                'success' => true,
                'message' => 'Refreshed successfully',
                'last_refreshed_at' => $now->toIso8601String(),
                "code" => 201,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refresh failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Internal server error',
                'code' => 500,
            ];
        }
    }


    private function fetchCountries(): array
    {
        try {
            $countriesResponse = Http::timeout($this->timeout)
                ->get($this->countriesApi);

            // dd($countriesResponse->json());

            if (!$countriesResponse->successful()) {
                Log::error("Countries Api Failed: " . $countriesResponse->status());
                return [
                    "success" => false,
                    "message" => "Could not fetch countries",
                    "api" => "countries",
                    "code" => 503,
                ];
            }

            return [
                "success" => true,
                "data" => $countriesResponse->json(),
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function fetchExchangeRateApi(): array
    {
        try {
            $exchangeResponse = Http::timeout($this->timeout)
                ->get($this->exchangeApi);

            if (! $exchangeResponse->successful()) {
                Log::error('Exchange API failed: ' . $exchangeResponse->status());
                return [
                    'success' => false,
                    'message' => 'Could not fetch exchange rates',
                    'api' => 'exchange',
                    'code' => 503,
                ];
            }

            return [
                "success" => true,
                "data" => $exchangeResponse->json(),
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getAllCountries(array $data): array
    {
        try {
            $query = CountryCurrency::query();

            //? check if region exist
            if (isset($data['region']) && !empty($data['region'])) {
                $query->where("region", $data['region']);
            }

            //? check if currency exist
            if (isset($data['currency']) && !empty($data['currency'])) {
                $query->where("currency_code", $data['currency']);
            }

            //? check if sort exist
            if (isset($data['sort']) && !empty($data['sort'])) {
                if ($data['sort'] === "gdp_desc") {
                    $query->orderByDesc('estimated_gdp');
                } elseif ($data['sort'] === "gdp_asc") {
                    $query->orderBy('estimated_gdp');
                }
            }

            return [
                "success" => true,
                "countries" => $query->get()->makeHidden(["created_at", "updated_at"]),
                'code' => 200,
            ];
        } catch (\Exception $e) {
            Log::error("Get all countries failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => "Internal server error",
                'code' => 500,
            ];
        }
    }


    public function deleteCountry(string $countryName): array
    {
        try {
            $country = CountryCurrency::whereRaw("LOWER(name) = ?", [mb_strtolower($countryName)])
                ->first();

            if (!$country) {
                return [
                    "success" => false,
                    "message" => "Country not found",
                    "code" => 404,
                ];
            }

            $country->delete();

            return [
                "success" => true,
                "message" => "Deleted",
                "code" => 200,
            ];
        } catch (\Exception $e) {
            Log::error("Country deletion faild: " . $e->getMessage());

            return [
                'success' => false,
                'message' => "Internal server error",
                'code' => 500,
            ];
        }
    }

    public function findSingleCountry(string $countryName): array
    {
        try {
            $country = CountryCurrency::whereRaw("LOWER(name) = ?", [mb_strtolower($countryName)])
                ->first();

            if (!$country) {
                return [
                    "success" => false,
                    "message" => "Country not found",
                    "code" => 404,
                ];
            }

            return [
                "success" => true,
                "country" => $country->makeHidden(["created_at", "updated_at"]),
                "code" => 200,
            ];
        } catch (\Exception $e) {
            Log::error("Single country fecth failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => "Internal server error",
                'code' => 500,
            ];
        }
    }


    public function status(): array
    {
        try {
            $total = CountryCurrency::count();
            $last = CountryCurrency::orderByDesc('last_refreshed_at')->value('last_refreshed_at');

            return [
                "success" => true,
                'total_countries' => $total,
                'last_refreshed_at' => $last ? (new \Carbon\Carbon($last))->toIso8601String() : null,
                'code' => 200,
            ];
        } catch (\Exception $e) {
            Log::error("Status check failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => "Internal server error",
                'code' => 500,
            ];
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

        //? Prepare text lines
        $lines = [];
        $lines[] = "Total countries: {$total}";
        $lines[] = "Last refresh: " . $timestamp->toIso8601String();
        $lines[] = "Top 5 by estimated GDP:";
        foreach ($top5 as $i => $c) {
            $lines[] = ($i + 1) . ". {$c->name} — " . number_format($c->estimated_gdp, 2);
        }

        //? Create a simple PNG using GD and store to storage/app/public/cache/summary.png
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
        $path = storage_path($this->imagePath);
        imagepng($img, $path);
        imagedestroy($img);
    }
}