<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OllamaClient
{
    public function tags()
    {
        return $this->request('GET', '/api/tags');
    }

    public function show(string $model)
    {
        return $this->request('POST', '/api/show', ['model' => $model]);
    }

    public function pull(string $model)
    {
        return $this->request('POST', '/api/pull', ['model' => $model, 'stream' => false]);
    }

    public function remove(string $model)
    {
        return $this->request('POST', '/api/rm', ['model' => $model]);
    }

    public function health()
    {
        return $this->request('GET', '/');
    }

    private function request(string $method, string $path, array $payload = [])
    {
        $host = rtrim((string) config('ollama.host', 'http://localhost:11434'), '/');
        $url = $host . $path;
        $client = Http::timeout(0)->withOptions(['http_errors' => false]);

        if ($method === 'GET') {
            return $client->get($url);
        }

        return $client->send($method, $url, [
            'json' => $payload,
        ]);
    }
}
