<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Credentials
    |--------------------------------------------------------------------------
    |
    | Maps provider IDs (used in agents) to their API key env vars. Aligned
    | with Laravel AI SDK. Only providers with a non-empty key are shown
    | in the provider dropdown.
    |
    */

    'providers' => [
        'anthropic' => [
            'label' => 'Anthropic (Claude)',
            'key' => env('ANTHROPIC_API_KEY'),
        ],
        'openai' => [
            'label' => 'OpenAI',
            'key' => env('OPENAI_API_KEY'),
        ],
        'gemini' => [
            'label' => 'Google (Gemini)',
            'key' => env('GEMINI_API_KEY'),
        ],
        'groq' => [
            'label' => 'Groq',
            'key' => env('GROQ_API_KEY'),
        ],
        'xai' => [
            'label' => 'xAI (Grok)',
            'key' => env('XAI_API_KEY'),
        ],
        'mistral' => [
            'label' => 'Mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],
        'cohere' => [
            'label' => 'Cohere',
            'key' => env('COHERE_API_KEY'),
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],
    ],

];
