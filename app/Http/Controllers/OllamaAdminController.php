<?php

namespace App\Http\Controllers;

use App\Services\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OllamaAdminController
{
    public function tags(OllamaClient $client): JsonResponse
    {
        $response = $client->tags();

        return response()->json($response->json(), $response->status());
    }

    public function show(Request $request, OllamaClient $client): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
        ]);

        $response = $client->show($validated['model']);

        return response()->json($response->json(), $response->status());
    }

    public function pull(Request $request, OllamaClient $client): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
        ]);

        $response = $client->pull($validated['model']);

        return response()->json($response->json(), $response->status());
    }

    public function remove(Request $request, OllamaClient $client): JsonResponse
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
        ]);

        $response = $client->remove($validated['model']);

        return response()->json($response->json(), $response->status());
    }
}
