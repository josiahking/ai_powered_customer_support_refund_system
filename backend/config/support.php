<?php

return [
    'username' => (string) env('SUPPORT_USERNAME', 'support'),
    'password' => (string) env('SUPPORT_PASSWORD', ''),
    'auth_ttl_minutes' => max(1, min(1440, (int) env('SUPPORT_AUTH_TTL_MINUTES', 480))),
    'cookie_name' => (string) env('SUPPORT_COOKIE_NAME', 'refund_support_auth'),
    'cookie_secure' => (bool) env('SUPPORT_COOKIE_SECURE', false),
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),
];
