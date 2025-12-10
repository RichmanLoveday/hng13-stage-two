<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = null): Response
    {
        //? If API key exists → validate API key
        if ($request->hasHeader('x-api-key')) {
            $apiKey = ApiKey::where('key', hash('sha256', $request->header('x-api-key')))
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$apiKey) {
                return response()->json(['error' => 'Invalid or expired API key'], 401);
            }

            if ($permission && !in_array($permission, $apiKey->permissions)) {
                return response()->json(['error' => 'Missing permission'], 403);
            }

            //? Attach user to request
            $request->setUserResolver(fn() => $apiKey->user);

            return $next($request);
        }

        //? Else fallback to Bearer token
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token not parsable'], 401);
        }

        if (!$user) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        //? Attach JWT user safely to the request
        $request->setUserResolver(fn() => $user);

        return $next($request);
    }
}