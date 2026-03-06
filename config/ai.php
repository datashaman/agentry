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
            'models' => [
                'claude-opus-4-6' => 'Claude Opus 4.6',
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                'claude-sonnet-4-5' => 'Claude Sonnet 4.5',
                'claude-haiku-4-5' => 'Claude Haiku 4.5',
            ],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'key' => env('OPENAI_API_KEY'),
            'models' => [
                'gpt-5.2' => 'GPT-5.2',
                'gpt-5-mini' => 'GPT-5 Mini',
                'gpt-5-nano' => 'GPT-5 Nano',
            ],
        ],
        'gemini' => [
            'label' => 'Google (Gemini)',
            'key' => env('GEMINI_API_KEY'),
            'models' => [
                'gemini-2.5-pro' => 'Gemini 2.5 Pro',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro (Preview)',
            ],
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
