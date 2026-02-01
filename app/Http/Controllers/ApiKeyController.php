<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController
{
    public function index(): JsonResponse
    {
        $keys = ApiKey::with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKey $key) => [
                'id' => $key->id,
                'name' => $key->name,
                'last_four' => $key->last_four,
                'user' => $key->user?->only(['id', 'name', 'email']),
                'last_used_at' => optional($key->last_used_at)->toISOString(),
                'expires_at' => optional($key->expires_at)->toISOString(),
                'revoked_at' => optional($key->revoked_at)->toISOString(),
                'created_at' => optional($key->created_at)->toISOString(),
            ]);

        return response()->json(['keys' => $keys]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $plain = 'ogw_'.Str::random(40);

        $key = ApiKey::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'token_hash' => hash('sha256', $plain),
            'last_four' => substr($plain, -4),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return response()->json([
            'key' => [
                'id' => $key->id,
                'name' => $key->name,
                'last_four' => $key->last_four,
                'created_at' => optional($key->created_at)->toISOString(),
            ],
            'token' => $plain,
        ], 201);
    }

    public function revoke(ApiKey $apiKey): JsonResponse
    {
        $apiKey->forceFill(['revoked_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}
