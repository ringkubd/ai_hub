<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OllamaGatewayAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('ollama.gateway_key');
        $allowLocal = (bool) config('ollama.allow_unauthenticated_local');

        if ($allowLocal && $this->isLocalRequest($request)) {
            return $next($request);
        }

        $token = $this->extractToken($request);

        if ($token === null || $token === '') {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        if ($expectedKey !== '' && hash_equals($expectedKey, $token)) {
            return $next($request);
        }

        if ($this->validateApiKey($token)) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized.'], 401);

    }

    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        $apiKeyHeader = $request->header('X-API-Key');
        if (is_string($apiKeyHeader) && $apiKeyHeader !== '') {
            return trim($apiKeyHeader);
        }

        return null;
    }

    private function isLocalRequest(Request $request): bool
    {
        $ip = $request->ip();

        return $ip === '127.0.0.1' || $ip === '::1';
    }

    private function validateApiKey(string $token): bool
    {
        $hash = hash('sha256', $token);

        $apiKey = ApiKey::where('token_hash', $hash)->first();

        if (! $apiKey || ! $apiKey->isValid()) {
            return false;
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        return true;
    }
}
