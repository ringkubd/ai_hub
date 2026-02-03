<?php

namespace App\Services;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QdrantProxy
{
    public function proxy(Request $request, string $path)
    {
        $base = rtrim((string) config('aihub.qdrant.url'), '/');
        $path = ltrim($path, '/');
        $url = $base.'/'.$path;

        if ($request->getQueryString()) {
            $url .= '?'.$request->getQueryString();
        }

        $method = strtoupper($request->method());
        $body = $request->getContent();
        $body = is_string($body) ? $body : '';

        $response = $this->send($request, $url, $method, $body);

        return response($response->body(), $response->status())
            ->withHeaders($this->filterResponseHeaders($response->headers()));
    }

    private function send(Request $request, string $url, string $method, string $body): HttpResponse
    {
        $client = Http::withHeaders($this->forwardHeaders($request))
            ->withOptions(['http_errors' => false])
            ->timeout(0);

        $options = [];
        if ($body !== '' && ! in_array($method, ['GET', 'HEAD'], true)) {
            $options['body'] = $body;
        }

        $apiKey = (string) config('aihub.qdrant.api_key');
        if ($apiKey !== '') {
            $client = $client->withHeaders(['api-key' => $apiKey]);
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
            $headers['cookie'],
            $headers['x-csrf-token']
        );

        return collect($headers)
            ->mapWithKeys(fn (array $values, string $key) => [$key => implode(', ', $values)])
            ->all();
    }

    private function filterResponseHeaders(array $headers): array
    {
        $blocked = ['transfer-encoding', 'content-length', 'connection'];

        return collect($headers)
            ->reject(fn (array $values, string $key) => in_array(strtolower($key), $blocked, true))
            ->mapWithKeys(fn (array $values, string $key) => [$key => implode(', ', $values)])
            ->all();
    }
}
