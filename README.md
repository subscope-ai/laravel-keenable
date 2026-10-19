# Laravel Keenable

Laravel client for the [Keenable](https://docs.keenable.ai) search and fetch API (`POST /v1/search`, `GET /v1/fetch`).

## Installation

```bash
composer require subscope-ai/laravel-keenable
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=keenable-config
```

## Configuration

`config/keenable.php` / env:

| Key | Env | Default |
| --- | --- | --- |
| `api_key` | `KEENABLE_API_KEY` | `null` |
| `base_url` | `KEENABLE_BASE_URL` | `https://api.keenable.ai` |
| `app_title` | `KEENABLE_APP_TITLE` | `APP_NAME` |
| `billing` | `KEENABLE_BILLING` | `auto` (`key` when a key is set, `public` otherwise; `x402` for pay-per-request paths) |
| `default_mode` | `KEENABLE_SEARCH_MODE` | `pro` |
| `wrap_tool_exceptions` | `KEENABLE_WRAP_TOOL_EXCEPTIONS` | `true` |

```env
KEENABLE_API_KEY=keen_...
KEENABLE_APP_TITLE="My App"
```

Without a key the public endpoints are used, which require `app_title` (sent as `X-Keenable-Title`, max 256 characters — longer values are rejected client-side).

## Usage

Facade:

```php
use SubscopeAi\Keenable\Facades\Keenable;

$results = Keenable::search('laravel best practices', [
    'mode' => 'pro',          // pro|realtime
    'max_results' => 10,      // 1-50, omit for server default
    // acquired_after, acquired_before, published_after,
    // published_before, query_time, snippet_max_length (180-10000)
]);

foreach ($results->results as $hit) {
    echo $hit->title.' '.$hit->url;
}

$page = Keenable::fetch('https://example.test/resource', [
    'live' => true,           // fetch live, incl. non-indexed URLs
    'max_chars' => 50000,
    'prompt' => 'Extract key points', // max 2000 chars, LLM-extracted content
]);

echo $page->content;
```

DI:

```php
use SubscopeAi\Keenable\KeenableClient;

public function __construct(protected KeenableClient $keenable) {}
```

To use the `x402` paths (`/v1/x402/*`), set `KEENABLE_BILLING=x402` — payment handling stays on the caller.

## AI tools

The package ships two [Laravel AI](https://github.com/laravel/ai) tools: `KeenableSearchTool` and `KeenableFetchTool`. They implement `Laravel\Ai\Contracts\Tool`; the service provider binds them with `KeenableClient` and the package config. By default provider errors are reported and the tool returns an empty result (`[]` for search, `''` for fetch); set `keenable.wrap_tool_exceptions` to `false`, or pass `wrapExceptions: false` to the constructor, to let typed exceptions bubble up.

```php
use SubscopeAi\Keenable\Tools\KeenableFetchTool;
use SubscopeAi\Keenable\Tools\KeenableSearchTool;

$agent->tools([
    app(KeenableSearchTool::class),
    app(KeenableFetchTool::class),
]);

// Propagate provider exceptions instead of returning an empty result.
new KeenableSearchTool($client, wrapExceptions: false);
```

Enums and DTOs:

```php
use SubscopeAi\Keenable\Enums\SearchMode;

Keenable::search('...', ['mode' => SearchMode::Realtime]);
```

`search()` returns `SearchResponse` (`query`, `mode`, `SearchResult[]`), `fetch()` returns `FetchResponse` (`url`, `title`, `description`, `author`, `content`, `publishedAt`). `SearchResponse::$mode` is `?string`: it echoes the mode that served the request (`pro`/`realtime`), or `null` when the API did not report one — a missing mode is never guessed.

## Exceptions

All API errors extend `SubscopeAi\Keenable\Exceptions\KeenableException` (`statusCode()`, `responseBody()`, `responseHeaders()`):

| Exception | When |
| --- | --- |
| `AuthenticationException` | 401 missing/invalid key, or missing key (before request) |
| `MissingAppIdentifierException` | missing/empty app title in public mode (before request) |
| `NoCreditsException` | 402 |
| `ForbiddenException` | 403 revoked/disabled key |
| `RateLimitExceededException` | 429 (`retryAfter()`, `rateLimit()`, `rateLimitRemaining()`, `rateLimitReset()`) |
| `ValidationException` | any other 4xx from the API (message passed through untouched) |
| `ServerException` | 5xx |

Invalid arguments (empty query, bad URL, unknown `mode`/`billing`, `max_results` outside 1–50, `snippet_max_length` outside 180–10000, `prompt` over 2000 chars, app title over 256 chars) throw `InvalidArgumentException` before any HTTP call.

## Testing

```bash
composer test
# or
vendor/bin/pest
```

`Http::fake()` works as usual since the client is built on Laravel's HTTP client; `Keenable::search()` / `fetch()` go through `Http::assertSent()`.
