<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    public function embed(string $text): array
    {
        $cacheKey = 'aihub:emb:'.hash('sha256', $text);
        $ttl = (int) config('aihub.embedding_cache_ttl');

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $host = rtrim((string) config('ollama.host'), '/');
        $model = (string) config('aihub.embedding_model');
        $response = Http::timeout(0)
            ->withOptions(['http_errors' => false])
            ->post($host.'/api/embeddings', [
                'model' => $model,
                'prompt' => $text,
            ]);

        if (! $response->successful()) {
            \Log::warning('Ollama embedding failed', [
                'status' => $response->status(),
                'body' => substr((string) $response->body(), 0, 200),
            ]);
            return [];
        }

        $embedding = $response->json('embedding', []);
        if (is_array($embedding) && $embedding !== []) {
            Cache::put($cacheKey, $embedding, $ttl);
        }

        return $embedding;
    }
}
