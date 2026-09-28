<?php

namespace App\Http\Middleware;

use App\Services\SupportAuthToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSupportAuthentication
{
    public function __construct(private readonly SupportAuthToken $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie((string) config('support.cookie_name'));

        if (! is_string($token) || ! $this->tokens->isValid($token)) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
