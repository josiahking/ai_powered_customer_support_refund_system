<?php

namespace App\Http\Controllers;

use App\Services\SupportAuthToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SupportAuthController extends Controller
{
    public function login(Request $request, SupportAuthToken $tokens): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $configuredUsername = (string) config('support.username');
        $configuredPassword = (string) config('support.password');

        if ($configuredUsername === '' || $configuredPassword === '' || (string) config('app.key') === '') {
            return response()->json([
                'message' => 'Support authentication is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $usernameMatches = hash_equals($configuredUsername, $credentials['username']);
        $passwordMatches = hash_equals($configuredPassword, $credentials['password']);

        if (! $usernameMatches || ! $passwordMatches) {
            return response()->json([
                'message' => 'Invalid support credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $ttl = (int) config('support.auth_ttl_minutes');
        try {
            $token = $tokens->issue($configuredUsername);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Support authentication is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $cookie = cookie(
            (string) config('support.cookie_name'),
            $token,
            $ttl,
            '/',
            null,
            (bool) config('support.cookie_secure'),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );

        return response()->json(['authenticated' => true])->withCookie($cookie);
    }

    public function logout(): JsonResponse
    {
        $cookie = cookie(
            (string) config('support.cookie_name'),
            '',
            -1,
            '/',
            null,
            (bool) config('support.cookie_secure'),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );

        return response()->json(['authenticated' => false])->withCookie($cookie);
    }

    public function session(): JsonResponse
    {
        return response()->json(['authenticated' => true]);
    }
}
