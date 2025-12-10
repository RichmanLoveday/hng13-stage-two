<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }


    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // dd($googleUser->getId());

            $user = User::where('email', $googleUser->getEmail())->first();

            // dd($user->toArray());

            if (!$user) {
                DB::transaction(function () use ($googleUser, &$user) {
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'email_verified_at' => now(),
                        'password' => bcrypt(str()->random(16)), // dummy password
                    ]);

                    // create wallet for the new user
                    Wallet::create([
                        'user_id' => $user->id,
                        'wallet_number' => 'WL' . strtoupper(Str::random(10)),
                        'balance' => 0
                    ]);
                });
            }

            // Force login for JWT
            $token = JWTAuth::fromUser($user);
            // dd($token);

            return response()->json([
                'status' => true,
                'message' => 'Authentication successful',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'user' => $user->wallet ? $user->load('wallet') : $user,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Google authentication failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}