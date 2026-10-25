<?php

return [
    // Issuer OIDC (tanpa trailing slash). Harus stabil: dipakai sebagai klaim `iss`.
    'issuer' => rtrim(env('IAM_ISSUER', env('APP_URL', 'http://localhost')), '/'),

    'access_token_ttl' => (int) env('IAM_ACCESS_TTL', 900),            // 15 menit
    'id_token_ttl' => (int) env('IAM_ID_TTL', 900),
    'refresh_token_ttl' => (int) env('IAM_REFRESH_TTL', 60 * 60 * 24 * 30), // 30 hari
    'auth_code_ttl' => (int) env('IAM_CODE_TTL', 60),
    'sso_session_ttl' => (int) env('IAM_SSO_SESSION_TTL', 60 * 60 * 8),

    'scopes_supported' => ['openid', 'profile', 'email', 'offline_access'],

    'api_key_rate_per_minute' => (int) env('IAM_API_KEY_RATE', 120),

    'login_max_attempts' => 5,
    'login_decay_seconds' => 300,
];

