<?php

return [
    'host' => env('OLLAMA_HOST', 'http://127.0.0.1:11434'),
    'gateway_key' => env('OLLAMA_GATEWAY_KEY', ''),
    'allow_unauthenticated_local' => env('OLLAMA_ALLOW_UNAUTHENTICATED_LOCAL', false),
    'cache' => [
        'enabled' => env('OLLAMA_CACHE_ENABLED', true),
        'store' => env('OLLAMA_CACHE_STORE', ''),
        'ttl' => [
            'tags' => env('OLLAMA_CACHE_TTL_TAGS', 60),
            'show' => env('OLLAMA_CACHE_TTL_SHOW', 300),
            'embeddings' => env('OLLAMA_CACHE_TTL_EMBEDDINGS', 60 * 60 * 24 * 30),
            'generate' => env('OLLAMA_CACHE_TTL_GENERATE', 900),
            'chat' => env('OLLAMA_CACHE_TTL_CHAT', 900),
        ],
    ],
];
