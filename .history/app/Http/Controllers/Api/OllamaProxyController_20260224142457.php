<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OllamaProxyController extends Controller
{
    protected Client $client;
    protected string $ollamaBaseUrl;

    public function __construct()
    {
        $this->ollamaBaseUrl = rtrim(config('ai.providers.ollama.base_url', env('OLLAMA_BASE_URL', 'http://localhost:11434')), '/');
        $this->client = new Client([
            'timeout' => 300,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Chat completion endpoint - supports streaming
     */
    public function chat(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'model' => ['required', 'string'],
            'messages' => ['required', 'array'],
            'stream' => ['nullable', 'boolean'],
            'format' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'keep_alive' => ['nullable', 'string'],
        ]);

        if ($data['stream'] ?? false) {
            return $this->streamRequest('/api/chat', $data);
        }

        return $this->proxyRequest('/api/chat', $data);
    }

    /**
     * Generate completion endpoint - supports streaming
     */
    public function generate(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'model' => ['required', 'string'],
            'prompt' => ['required', 'string'],
            'stream' => ['nullable', 'boolean'],
            'format' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'system' => ['nullable', 'string'],
            'template' => ['nullable', 'string'],
            'context' => ['nullable', 'array'],
            'raw' => ['nullable', 'boolean'],
            'keep_alive' => ['nullable', 'string'],
        ]);

        if ($data['stream'] ?? false) {
            return $this->streamRequest('/api/generate', $data);
        }

        return $this->proxyRequest('/api/generate', $data);
    }

    /**
     * List available models
     */
    public function tags(): JsonResponse
    {
        return $this->proxyRequest('/api/tags');
    }

    /**
     * Show model information
     */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
        ]);

        return $this->proxyRequest('/api/show', $data);
    }

    /**
     * Generate embeddings
     */
    public function embeddings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model' => ['required', 'string'],
            'prompt' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'keep_alive' => ['nullable', 'string'],
        ]);

        return $this->proxyRequest('/api/embeddings', $data);
    }

    /**
     * Pull a model from the library
     */
    public function pull(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'insecure' => ['nullable', 'boolean'],
            'stream' => ['nullable', 'boolean'],
        ]);

        if ($data['stream'] ?? true) {
            return $this->streamRequest('/api/pull', $data);
        }

        return $this->proxyRequest('/api/pull', $data);
    }

    /**
     * Push a model to the library
     */
    public function push(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'insecure' => ['nullable', 'boolean'],
            'stream' => ['nullable', 'boolean'],
        ]);

        if ($data['stream'] ?? true) {
            return $this->streamRequest('/api/push', $data);
        }

        return $this->proxyRequest('/api/push', $data);
    }

    /**
     * Delete a model
     */
    public function delete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
        ]);

        return $this->proxyRequest('/api/delete', $data, 'DELETE');
    }

    /**
     * Copy a model
     */
    public function copy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string'],
            'destination' => ['required', 'string'],
        ]);

        return $this->proxyRequest('/api/copy', $data);
    }

    /**
     * Create a model from a Modelfile
     */
    public function create(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'modelfile' => ['nullable', 'string'],
            'stream' => ['nullable', 'boolean'],
            'path' => ['nullable', 'string'],
        ]);

        if ($data['stream'] ?? true) {
            return $this->streamRequest('/api/create', $data);
        }

        return $this->proxyRequest('/api/create', $data);
    }

    /**
     * Check if Ollama server is running
     */
    public function health(): JsonResponse
    {
        try {
            $response = $this->client->get($this->ollamaBaseUrl . '/');

            return response()->json([
                'status' => 'ok',
                'ollama_running' => true,
                'response' => $response->getBody()->getContents(),
            ]);
        } catch (GuzzleException $e) {
            return response()->json([
                'status' => 'error',
                'ollama_running' => false,
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Proxy a request to Ollama
     */
    protected function proxyRequest(string $endpoint, array $data = [], string $method = 'POST'): JsonResponse
    {
        try {
            $options = ['json' => $data];

            if ($method === 'GET') {
                $options = ['query' => $data];
            }

            $response = $this->client->request(
                $method,
                $this->ollamaBaseUrl . $endpoint,
                $options
            );

            $body = json_decode($response->getBody()->getContents(), true);

            return response()->json($body, $response->getStatusCode());
        } catch (GuzzleException $e) {
            Log::error('Ollama proxy error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Ollama service error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream a request from Ollama
     */
    protected function streamRequest(string $endpoint, array $data): StreamedResponse
    {
        return response()->stream(function () use ($endpoint, $data) {
            try {
                $stream = $this->client->post(
                    $this->ollamaBaseUrl . $endpoint,
                    [
                        'json' => $data,
                        'stream' => true,
                    ]
                );

                $body = $stream->getBody();

                while (! $body->eof()) {
                    $line = $body->read(8192);
                    echo $line;

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (GuzzleException $e) {
                Log::error('Ollama streaming error', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);

                echo json_encode(['error' => $e->getMessage()]);

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
