<?php

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 20),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
];
