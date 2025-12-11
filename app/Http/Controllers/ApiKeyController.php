<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'permissions' => 'required|array',
            'expiry' => 'required|in:1H,1D,1M,1Y'
        ]);
        // dd($request->all());

        try {
            $user = auth()->user();

            if ($user->apiKeys()->where('revoked', false)->count() >= 5) {
                return response()->json([
                    'status' => false,
                    'message' => 'Maximum of 5 active API keys allowed'
                ], 400);
            }

            $plainKey = 'sk_' . bin2hex(random_bytes(16));
            $hashedKey = hash('sha256', $plainKey);

            // dd($plainKey, $hashedKey);

            //? create api key
            $apiKey = ApiKey::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'key' => $hashedKey,
                'permissions' => $request->permissions,
                'expires_at' => $this->parseExpiry($request->expiry)
            ]);

            return response()->json([
                'api_key' => $plainKey,
                'expires_at' => $apiKey->expires_at
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function rollover(Request $request)
    {
        try {
            $request->validate([
                'expired_key_id' => 'required|exists:api_keys,id',
                'expiry' => 'required|in:1H,1D,1M,1Y'
            ]);

            // dd($request->all());

            $expiredKey = ApiKey::where('id', $request->expired_key_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();


            // dd($expiredKey->revoked);
            if ($expiredKey->expires_at->isFuture() &&  !$expiredKey->revoked) {
                return response()->json([
                    'status' => false,
                    'message' => 'Key has not expired yet or is not revoked'
                ], 400);
            }

            $plainKey = 'sk_' . Str::random(40);

            $newKey = ApiKey::create([
                'user_id' => auth()->id(),
                'name' => $expiredKey->name,
                'key' => hash('sha256', $plainKey),
                'permissions' => $expiredKey->permissions,
                'expires_at' => $this->parseExpiry($request->expiry)
            ]);

            return response()->json([
                'api_key' => $plainKey,
                'expires_at' => $newKey->expires_at
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function revoke(Request $request)
    {
        try {
            $request->validate([
                'key_id' => 'required|exists:api_keys,id'
            ]);

            $apiKey = ApiKey::where('id', $request->key_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $apiKey->revoked = true;
            $apiKey->save();

            return response()->json([
                'status' => true,
                'message' => 'API key revoked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function list(Request $request)
    {
        try {
            $apiKeys = ApiKey::where('user_id', auth()->id())
                ->get(['id', 'name', 'permissions', 'expires_at', 'revoked', 'created_at']);

            return response()->json([
                'status' => true,
                'api_keys' => $apiKeys
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    private function parseExpiry(string $expiry): Carbon
    {
        return match ($expiry) {
            '1H' => now()->addHour(),
            '1D' => now()->addDay(),
            '1M' => now()->addMonth(),
            '1Y' => now()->addYear(),
            default => throw new \Exception('Invalid expiry format')
        };
    }
}