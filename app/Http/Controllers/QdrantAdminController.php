<?php

namespace App\Http\Controllers;

use App\Services\QdrantClient;
use App\Services\QdrantProxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QdrantAdminController
{
    public function collections(QdrantClient $client): JsonResponse
    {
        $response = $client->collections();

        return response()->json($response->json(), $response->status());
    }

    public function collectionInfo(Request $request, QdrantClient $client): JsonResponse
    {
        $name = $request->route('name');
        $response = $client->collectionInfo((string) $name);

        return response()->json($response->json(), $response->status());
    }

    public function createCollection(Request $request, QdrantClient $client): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $response = $client->createCollection($data['name'], $data['size']);

        return response()->json($response->json(), $response->status());
    }

    public function deleteCollection(Request $request, QdrantClient $client): JsonResponse
    {
        $name = $request->route('name');
        $response = $client->deleteCollection((string) $name);

        return response()->json($response->json(), $response->status());
    }

    public function proxy(Request $request, string $path = '')
    {
        return app(QdrantProxy::class)->proxy($request, $path);
    }

    public function health(QdrantClient $client): JsonResponse
    {
        $response = $client->health();

        return response()->json($response->json(), $response->status());
    }
}
