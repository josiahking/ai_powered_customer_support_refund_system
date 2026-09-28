<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'model' => env('AI_MODEL', 'gemini-3.8-flash'),
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 20),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],
];
