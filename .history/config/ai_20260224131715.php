<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_TEXT_PROVIDER', 'ollama'),
    'default_for_images' => env('AI_IMAGE_PROVIDER', 'openai'),
    'default_for_audio' => env('AI_AUDIO_PROVIDER', 'ollama'),
    'default_for_transcription' => env('AI_TRANSCRIPTION_PROVIDER', 'ollama'),
    'default_for_embeddings' => env('AI_EMBEDDINGS_PROVIDER', 'ollama'),
    'default_for_reranking' => env('AI_RERANKING_PROVIDER', 'ollama'),

    /*
    |--------------------------------------------------------------------------
    | Default Models
    |--------------------------------------------------------------------------
    |
    | Centralized model selection for your application. These values are also
    | mapped into provider-specific model configuration below so they are used
    | automatically whenever a model is not explicitly passed in code.
    |
    */

    'models' => [
        'text' => [
            'default' => env('AI_TEXT_MODEL', 'llama3.2:3b'),
            'cheapest' => env('AI_TEXT_MODEL_CHEAPEST', env('AI_TEXT_MODEL', 'llama3.2:3b')),
            'smartest' => env('AI_TEXT_MODEL_SMARTEST', env('AI_TEXT_MODEL', 'llama3.2:3b')),
            'tools_default' => env('AI_TEXT_TOOLS_MODEL', 'llama3:1b'),
        ],
        'embeddings' => [
            'default' => env('AI_EMBEDDING_MODEL', 'nomic-embed-text'),
            'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 768),
        ],
        'image' => [
            'default' => env('AI_IMAGE_MODEL', 'gpt-image-1.5'),
        ],
        'audio' => [
            'default' => env('AI_AUDIO_MODEL', 'gpt-4o-mini-tts'),
        ],
        'transcription' => [
            'default' => env('AI_TRANSCRIPTION_MODEL', 'gpt-4o-transcribe-diarize'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'models' => [
                'text' => [
                    'default' => env('AI_OLLAMA_TEXT_MODEL', env('AI_TEXT_MODEL', 'llama3.2:3b')),
                    'cheapest' => env('AI_OLLAMA_TEXT_MODEL_CHEAPEST', env('AI_TEXT_MODEL_CHEAPEST', env('AI_TEXT_MODEL', 'llama3.2:3b'))),
                    'smartest' => env('AI_OLLAMA_TEXT_MODEL_SMARTEST', env('AI_TEXT_MODEL_SMARTEST', env('AI_TEXT_MODEL', 'llama3.2:3b'))),
                    'tools_default' => env('AI_OLLAMA_TEXT_TOOLS_MODEL', env('AI_TEXT_TOOLS_MODEL', 'llama3.2:1b')),
                ],
                'embeddings' => [
                    'default' => env('AI_OLLAMA_EMBEDDING_MODEL', env('AI_EMBEDDING_MODEL', 'nomic-embed-text')),
                    'dimensions' => (int) env('AI_OLLAMA_EMBEDDING_DIMENSIONS', env('AI_EMBEDDING_DIMENSIONS', 768)),
                ],
            ],
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('AI_OPENAI_TEXT_MODEL', env('AI_TEXT_MODEL', 'gpt-5.2')),
                    'cheapest' => env('AI_OPENAI_TEXT_MODEL_CHEAPEST', env('AI_TEXT_MODEL_CHEAPEST', 'gpt-5-nano')),
                    'smartest' => env('AI_OPENAI_TEXT_MODEL_SMARTEST', env('AI_TEXT_MODEL_SMARTEST', 'gpt-5.2-pro')),
                ],
                'image' => [
                    'default' => env('AI_OPENAI_IMAGE_MODEL', env('AI_IMAGE_MODEL', 'gpt-image-1.5')),
                ],
                'audio' => [
                    'default' => env('AI_OPENAI_AUDIO_MODEL', env('AI_AUDIO_MODEL', 'gpt-4o-mini-tts')),
                ],
                'transcription' => [
                    'default' => env('AI_OPENAI_TRANSCRIPTION_MODEL', env('AI_TRANSCRIPTION_MODEL', 'gpt-4o-transcribe-diarize')),
                ],
                'embeddings' => [
                    'default' => env('AI_OPENAI_EMBEDDING_MODEL', env('AI_EMBEDDING_MODEL', 'text-embedding-3-small')),
                    'dimensions' => (int) env('AI_OPENAI_EMBEDDING_DIMENSIONS', env('AI_EMBEDDING_DIMENSIONS', 1536)),
                ],
            ],
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

];
