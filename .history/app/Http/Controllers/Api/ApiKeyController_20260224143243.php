<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    /**
     * List all API keys for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $apiKeys = ApiKey::where('user_id', $request->user()->id)
            ->with('package')
            ->latest()
            ->get();

        return response()->json($apiKeys);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'api_package_id' => ['nullable', 'exists:api_packages,id'],
            'capabilities' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'rate_limit_override' => ['nullable', 'integer', 'min:1'],
        ]);

        // Generate a random token that user will see once
        $plainTextToken = 'sk_' . Str::random(48);
        
        $apiKey = ApiKey::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'key' => hash('sha256', $plainTextToken),
            'prefix' => substr($plainTextToken, 0, 10),
        ]);

        $apiKey->load('package');

        return response()->json([
            'api_key' => $apiKey,
            'plain_text_token' => $plainTextToken,
            'message' => 'API key created successfully. Save the token now - it won\'t be shown again!',
        ], 201);
    }

    /**
     * Show a specific API key
     */
    public function show(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure user owns this API key
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $apiKey->load('package');

        return response()->json($apiKey);
    }

    /**
     * Update an API key
     */
    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure user owns this API key
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'api_package_id' => ['nullable', 'exists:api_packages,id'],
            'capabilities' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'is_active' => ['sometimes', 'boolean'],
            'rate_limit_override' => ['nullable', 'integer', 'min:1'],
        ]);

        $apiKey->update($validated);
        $apiKey->load('package');

        return response()->json([
            'api_key' => $apiKey,
            'message' => 'API key updated successfully',
        ]);
    }

    /**
     * Delete an API key
     */
    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure user owns this API key
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $apiKey->delete();

        return response()->json([
            'message' => 'API key deleted successfully',
        ]);
    }

    /**
     * Regenerate an API key (creates new token, invalidates old one)
     */
    public function regenerate(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure user owns this API key
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Generate new token
        $plainTextToken = 'sk_' . Str::random(48);
        
        $apiKey->update([
            'key' => hash('sha256', $plainTextToken),
            'prefix' => substr($plainTextToken, 0, 10),
            'usage_count' => 0,
            'last_used_at' => null,
        ]);

        return response()->json([
            'api_key' => $apiKey,
            'plain_text_token' => $plainTextToken,
            'message' => 'API key regenerated successfully. Save the new token now!',
        ]);
    }

    /**
     * Get usage statistics for a specific API key
     */
    public function usage(Request $request, ApiKey $apiKey): JsonResponse
    {
        // Ensure user owns this API key
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $days = $request->input('days', 30);

        $logs = $apiKey->usageLogs()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return response()->json([
            'total_requests' => $logs->count(),
            'successful_requests' => $logs->where('status_code', '>=', 200)->where('status_code', '<', 300)->count(),
            'failed_requests' => $logs->where('status_code', '>=', 400)->count(),
            'avg_response_time' => round($logs->avg('response_time'), 2),
            'endpoints' => $logs->groupBy('endpoint')->map->count(),
        ]);
    }
}
