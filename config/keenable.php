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
    | Must not exceed 256 characters.
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
    | Default search mode
    |--------------------------------------------------------------------------
    */
    'default_mode' => env('KEENABLE_SEARCH_MODE', 'pro'),

    /*
    |--------------------------------------------------------------------------
    | Wrap tool exceptions
    |--------------------------------------------------------------------------
    |
    | When enabled, the AI tools report client exceptions and return an empty
    | result instead of throwing. Disable to let exceptions propagate.
    |
    */
    'wrap_tool_exceptions' => env('KEENABLE_WRAP_TOOL_EXCEPTIONS', true),
];
