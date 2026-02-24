<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackApiUsage
{
    /**
     * Handle an incoming request and track API usage
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Get the API key from the request (Sanctum token)
        $user = $request->user();
        $token = $request->bearerToken();
        
        // Find the API key if it exists
        $apiKey = null;
        if ($user && $token) {
            $hashedToken = hash('sha256', $token);
            $apiKey = ApiKey::where('key', $hashedToken)
                ->where('user_id', $user->id)
                ->first();
        }

        // Process the request
        $response = $next($request);
        
        // Calculate response time
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        // Log the usage asynchronously
        if ($user) {
            try {
                $this->logUsage(
                    $apiKey,
                    $user->id,
                    $request,
                    $response,
                    $responseTime
                );

                // Increment API key usage counter
                if ($apiKey) {
                    $apiKey->incrementUsage();
                }
            } catch (\Exception $e) {
                Log::error('Failed to log API usage', [
                    'error' => $e->getMessage(),
                    'endpoint' => $request->path(),
                ]);
            }
        }

        return $response;
    }

    /**
     * Log API usage to database
     */
    protected function logUsage(
        ?ApiKey $apiKey,
        int $userId,
        Request $request,
        Response $response,
        int $responseTime
    ): void {
        // Sanitize request data (remove sensitive info)
        $requestData = $this->sanitizeData($request->except([
            'password',
            'token',
            'secret',
            'api_key',
        ]));

        // Get response data if JSON
        $responseData = null;
        $content = $response->getContent();
        if ($content && $this->isJson($content)) {
            $responseData = $this->sanitizeData(json_decode($content, true));
        }

        ApiUsageLog::create([
            'api_key_id' => $apiKey?->id,
            'user_id' => $userId,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'response_time' => $responseTime,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => $requestData,
            'response_data' => $responseData,
            'error_message' => $response->isServerError() || $response->isClientError() 
                ? $content 
                : null,
        ]);
    }

    /**
     * Sanitize sensitive data from arrays
     */
    protected function sanitizeData(array|null $data): ?array
    {
        if (! $data) {
            return null;
        }

        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'api_key', 'authorization'];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            } elseif (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***REDACTED***';
            }
        }

        return $data;
    }

    /**
     * Check if string is valid JSON
     */
    protected function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
