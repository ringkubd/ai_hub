<?php

namespace App\Services;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OllamaProxy
{
    private const STREAM_CHUNK_SIZE = 8192;

    public function proxy(Request $request, string $path)
    {
        $host = rtrim((string) config('ollama.host'), '/');
        $path = ltrim($path, '/');
        $url = $host.'/'.$path;

        if ($request->getQueryString()) {
            $url .= '?'.$request->getQueryString();
        }

        $method = strtoupper($request->method());
        $body = $this->requestBody($request);
        $payload = $this->decodeJsonBody($request, $body);
        $streaming = $this->isStreaming($payload);

        $cacheMeta = $this->cacheMeta($path, $method);
        if ($cacheMeta['cacheable'] && ! $streaming) {
            $cached = $this->cacheStore()->get($this->cacheKey($method, $path, $request, $body));
            if (is_array($cached)) {
                return response($cached['body'], $cached['status'])
                    ->withHeaders($cached['headers']);
            }
        }

        if ($streaming) {
            return $this->streamUpstream($request, $url, $method, $body);
        }

        $response = $this->sendUpstream($request, $url, $method, $body);
        $headers = $this->filterResponseHeaders($response->headers());

        if ($cacheMeta['cacheable'] && $this->shouldCacheResponse($response)) {
            $ttl = $cacheMeta['ttl'];
            $this->cacheStore()->put(
                $this->cacheKey($method, $path, $request, $body),
                [
                    'status' => $response->status(),
                    'headers' => $headers,
                    'body' => $response->body(),
                ],
                $ttl,
            );
        }

        return response($response->body(), $response->status())
            ->withHeaders($headers);
    }

    private function streamUpstream(Request $request, string $url, string $method, string $body)
    {
        $response = $this->sendUpstream($request, $url, $method, $body, true);
        $headers = $this->filterResponseHeaders($response->headers());

        return response()->stream(function () use ($response): void {
            $stream = $response->toPsrResponse()->getBody();
            while (! $stream->eof()) {
                echo $stream->read(self::STREAM_CHUNK_SIZE);
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }
        }, $response->status(), $headers);
    }

    private function sendUpstream(Request $request, string $url, string $method, string $body, bool $stream = false): HttpResponse
    {
        $client = Http::withHeaders($this->forwardHeaders($request))
            ->withOptions([
                'http_errors' => false,
                'stream' => $stream,
            ])
            ->timeout(0);

        $options = [];
        if ($body !== '' && ! in_array($method, ['GET', 'HEAD'], true)) {
            $options['body'] = $body;
        }

        return $client->send($method, $url, $options);
    }

    private function forwardHeaders(Request $request): array
    {
        $headers = $request->headers->all();
        unset(
            $headers['host'],
            $headers['content-length'],
            $headers['authorization'],
            $headers['x-api-key']
        );

        return collect($headers)
            ->mapWithKeys(fn (array $values, string $key) => [$key => implode(', ', $values)])
            ->all();
    }

    private function filterResponseHeaders(array $headers): array
    {
        $blocked = [
            'transfer-encoding',
            'content-length',
            'connection',
        ];

        return collect($headers)
            ->reject(fn (array $values, string $key) => in_array(strtolower($key), $blocked, true))
            ->mapWithKeys(fn (array $values, string $key) => [$key => implode(', ', $values)])
            ->all();
    }

    private function requestBody(Request $request): string
    {
        $content = $request->getContent();

        return is_string($content) ? $content : '';
    }

    private function decodeJsonBody(Request $request, string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        if (! $request->isJson()) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function isStreaming(?array $payload): bool
    {
        return (bool) ($payload['stream'] ?? false);
    }

    private function cacheMeta(string $path, string $method): array
    {
        $path = trim($path, '/');
        $method = strtoupper($method);

        $ttlMap = [
            'tags' => (int) config('ollama.cache.ttl.tags'),
            'show' => (int) config('ollama.cache.ttl.show'),
            'embeddings' => (int) config('ollama.cache.ttl.embeddings'),
            'generate' => (int) config('ollama.cache.ttl.generate'),
            'chat' => (int) config('ollama.cache.ttl.chat'),
        ];

        $cacheable = config('ollama.cache.enabled') === true
            && array_key_exists($path, $ttlMap)
            && in_array($method, ['GET', 'POST'], true);

        return [
            'cacheable' => $cacheable,
            'ttl' => $ttlMap[$path] ?? 0,
        ];
    }

    private function cacheKey(string $method, string $path, Request $request, string $body): string
    {
        $query = $request->getQueryString() ?? '';
        $hash = hash('sha256', $method.'|'.$path.'|'.$query.'|'.$body);

        return 'ollama:cache:v1:'.$hash;
    }

    private function cacheStore()
    {
        $store = (string) config('ollama.cache.store');

        return $store !== '' ? Cache::store($store) : Cache::store();
    }

    private function shouldCacheResponse(HttpResponse $response): bool
    {
        return $response->status() >= 200 && $response->status() < 300;
    }
}
