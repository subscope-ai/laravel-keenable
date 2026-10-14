<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Keenable API key
    |--------------------------------------------------------------------------
    |
    | Workspace-scoped key (keen_...). When set, the keyed endpoints
    | (/v1/search, /v1/fetch) are used. When empty, the public keyless
    | endpoints (/v1/search/public, /v1/fetch/public) are used instead.
    |
    */
    'api_key' => env('KEENABLE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('KEENABLE_BASE_URL', 'https://api.keenable.ai'),

    /*
    |--------------------------------------------------------------------------
    | App title (keyless mode)
    |--------------------------------------------------------------------------
    |
    | Required on the public endpoints: sent as X-Keenable-Title.
    | Values longer than 256 characters are truncated by the API.
    |
    */
    'app_title' => env('KEENABLE_APP_TITLE', env('APP_NAME', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Billing / endpoint mode
    |--------------------------------------------------------------------------
    |
    | Supported: "auto" (keyed when api_key is set, public otherwise),
    | "key", "public", "x402". The x402 paths (/v1/x402/*) are paid
    | per request in USDC on Base; payment handling stays on the caller.
    |
    */
    'billing' => env('KEENABLE_BILLING', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Defaults for search
    |--------------------------------------------------------------------------
    */
    'default_mode' => env('KEENABLE_SEARCH_MODE', 'pro'),
    'default_max_results' => env('KEENABLE_MAX_RESULTS', 10),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */
    'timeout' => env('KEENABLE_TIMEOUT', 30),
    'connect_timeout' => env('KEENABLE_CONNECT_TIMEOUT', 10),
    'retry_times' => env('KEENABLE_RETRY_TIMES', 0),
    'retry_sleep_ms' => env('KEENABLE_RETRY_SLEEP_MS', 200),

    /*
    |--------------------------------------------------------------------------
    | Auth header
    |--------------------------------------------------------------------------
    |
    | "x-api-key" sends X-API-Key, "bearer" sends Authorization: Bearer.
    | When both headers are present the API prefers X-API-Key.
    |
    */
    'auth_header' => env('KEENABLE_AUTH_HEADER', 'x-api-key'),
];
