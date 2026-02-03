<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class QdrantClient
{
    public function createCollection(string $collection, int $size): Response
    {
        return $this->request('PUT', "/collections/{$collection}", [
            'vectors' => [
                'size' => $size,
                'distance' => 'Cosine',
            ],
        ]);
    }

    public function deleteCollection(string $collection): Response
    {
        return $this->request('DELETE', "/collections/{$collection}");
    }

    public function upsert(string $collection, array $points): Response
    {
        return $this->request('PUT', "/collections/{$collection}/points", [
            'points' => $points,
        ]);
    }

    public function search(string $collection, array $vector, int $limit): Response
    {
        return $this->request('POST', "/collections/{$collection}/points/search", [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'with_vectors' => false,
        ]);
    }

    public function collections(): Response
    {
        return $this->request('GET', '/collections');
    }

    public function collectionInfo(string $collection): Response
    {
        return $this->request('GET', "/collections/{$collection}");
    }

    public function health(): Response
    {
        return $this->request('GET', '/healthz');
    }

    private function request(string $method, string $path, array $payload = []): Response
    {
        $url = rtrim((string) config('aihub.qdrant.url'), '/').$path;
        $client = Http::timeout(0)->withOptions(['http_errors' => false]);
        $apiKey = (string) config('aihub.qdrant.api_key');
        if ($apiKey !== '') {
            $client = $client->withHeaders(['api-key' => $apiKey]);
        }

        $options = [];
        if ($payload !== []) {
            $options['json'] = $payload;
        }

        return $client->send($method, $url, $options);
    }
}
