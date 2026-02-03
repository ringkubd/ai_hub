<?php

return [
    'qdrant' => [
        'url' => env('QDRANT_URL', 'http://127.0.0.1:6333'),
        'api_key' => env('QDRANT_API_KEY', ''),
    ],
    'embedding_model' => env('AIHUB_EMBEDDING_MODEL', 'nomic-embed-text'),
    'llm_model' => env('AIHUB_LLM_MODEL', ''),
    'chunk' => [
        'size' => env('AIHUB_CHUNK_SIZE', 800),
        'overlap' => env('AIHUB_CHUNK_OVERLAP', 120),
    ],
    'retrieval' => [
        'top_k' => env('AIHUB_TOP_K', 6),
        'response_cache_ttl' => env('AIHUB_RESPONSE_CACHE_TTL', 600),
    ],
    'embedding_cache_ttl' => env('AIHUB_EMBEDDING_CACHE_TTL', 60 * 60 * 24 * 30),
];
