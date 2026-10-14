<?php

namespace SubscopeAi\Keenable;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use SubscopeAi\Keenable\Data\FetchResponse;
use SubscopeAi\Keenable\Data\SearchResponse;
use SubscopeAi\Keenable\Enums\SearchMode;
use SubscopeAi\Keenable\Exceptions\AuthenticationException;
use SubscopeAi\Keenable\Exceptions\ForbiddenException;
use SubscopeAi\Keenable\Exceptions\KeenableException;
use SubscopeAi\Keenable\Exceptions\MissingAppIdentifierException;
use SubscopeAi\Keenable\Exceptions\NoCreditsException;
use SubscopeAi\Keenable\Exceptions\RateLimitExceededException;
use SubscopeAi\Keenable\Exceptions\ServerException;
use SubscopeAi\Keenable\Exceptions\ValidationException;

class KeenableClient
{
    /**
     * @param  array{api_key?: string|null, base_url?: string, app_title?: string, billing?: string, default_mode?: string}  $config
     */
    public function __construct(
        protected HttpFactory $http,
        protected array $config = [],
    ) {
    }

    /**
     * Search the web and return ranked results.
     *
     * @param  array{
     *   mode?: SearchMode|string,
     *   site?: string,
     *   acquired_after?: string,
     *   acquired_before?: string,
     *   published_after?: string,
     *   published_before?: string,
     *   query_time?: string,
     *   snippet_max_length?: int,
     *   max_results?: int,
     * }  $options
     *
     * @throws AuthenticationException
     * @throws ConnectionException
     * @throws ForbiddenException
     * @throws InvalidArgumentException
     * @throws KeenableException
     * @throws MissingAppIdentifierException
     * @throws NoCreditsException
     * @throws RateLimitExceededException
     * @throws ServerException
     * @throws ValidationException
     */
    public function search(string $query, array $options = []): SearchResponse
    {
        if ($query === '') {
            throw new InvalidArgumentException('Search query must not be empty.');
        }

        $mode = $options['mode'] ?? $this->config['default_mode'] ?? SearchMode::Pro->value;
        $mode = $mode instanceof SearchMode ? $mode->value : $mode;

        if (! in_array($mode, [SearchMode::Pro->value, SearchMode::Realtime->value], true)) {
            throw new InvalidArgumentException("Invalid search mode [$mode]. Expected 'pro' or 'realtime'.");
        }

        if (isset($options['max_results'])
            && ($options['max_results'] < 1 || $options['max_results'] > 50)
        ) {
            throw new InvalidArgumentException('Option [max_results] must be between 1 and 50.');
        }

        if (isset($options['snippet_max_length'])) {
            $length = $options['snippet_max_length'];

            if ($length < 180 || $length > 10000) {
                throw new InvalidArgumentException('Option [snippet_max_length] must be between 180 and 10000.');
            }
        }

        $payload = array_filter([
            'query' => $query,
            'mode' => $mode,
            'site' => $options['site'] ?? null,
            'acquired_after' => $options['acquired_after'] ?? null,
            'acquired_before' => $options['acquired_before'] ?? null,
            'published_after' => $options['published_after'] ?? null,
            'published_before' => $options['published_before'] ?? null,
            'query_time' => $options['query_time'] ?? null,
            'snippet_max_length' => $options['snippet_max_length'] ?? null,
            'max_results' => $options['max_results'] ?? null,
        ], static fn ($value) => $value !== null);

        $response = $this->request('POST', $this->path('search'), [
            'json' => $payload,
        ]);

        return SearchResponse::fromArray($response);
    }

    /**
     * Fetch a page as Markdown.
     *
     * @param  array{
     *   max_chars?: int,
     *   live?: bool,
     *   prompt?: string,
     * }  $options
     *
     * @throws AuthenticationException
     * @throws ConnectionException
     * @throws ForbiddenException
     * @throws InvalidArgumentException
     * @throws KeenableException
     * @throws MissingAppIdentifierException
     * @throws NoCreditsException
     * @throws RateLimitExceededException
     * @throws ServerException
     * @throws ValidationException
     */
    public function fetch(string $url, array $options = []): FetchResponse
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Fetch URL must be a valid URL.');
        }

        if (isset($options['prompt']) && mb_strlen($options['prompt']) > 2000) {
            throw new InvalidArgumentException('Option [prompt] must not exceed 2000 characters.');
        }

        if (isset($options['max_chars']) && $options['max_chars'] < 1) {
            throw new InvalidArgumentException('Option [max_chars] must be a positive integer.');
        }

        $query = array_filter([
            'url' => $url,
            'max_chars' => $options['max_chars'] ?? null,
            'live' => $options['live'] ?? null,
            'prompt' => $options['prompt'] ?? null,
        ], static fn ($value) => $value !== null);

        $response = $this->request('GET', $this->path('fetch'), [
            'query' => $query,
        ]);

        return FetchResponse::fromArray($response);
    }

    /**
     * @param  array{json?: array<string, mixed>, query?: array<string, mixed>}  $options
     * @return array<string, mixed>
     *
     * @throws AuthenticationException
     * @throws ConnectionException
     * @throws ForbiddenException
     * @throws InvalidArgumentException
     * @throws KeenableException
     * @throws MissingAppIdentifierException
     * @throws NoCreditsException
     * @throws RateLimitExceededException
     * @throws ServerException
     * @throws ValidationException
     */
    protected function request(string $method, string $path, array $options = []): array
    {
        $billing = $this->config['billing'] ?? 'auto';

        $pending = $this->http
            ->baseUrl($this->config['base_url'] ?? 'https://api.keenable.ai')
            ->acceptJson()
            ->withHeaders($this->headers($billing));

        $response = match (strtoupper($method)) {
            'GET' => $pending->get($path, $options['query'] ?? []),
            default => $pending->post($path, $options['json'] ?? []),
        };

        if ($response->successful()) {
            $data = $response->json();

            return is_array($data) ? $data : [];
        }

        $this->throwForStatus($response);
    }

    /**
     * @return array<string, string>
     *
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws MissingAppIdentifierException
     */
    protected function headers(string $billing): array
    {
        $mode = $this->resolveBilling($billing);
        $headers = [];

        if ($mode === 'key') {
            $key = $this->config['api_key'] ?? '';

            if ($key === '') {
                throw new AuthenticationException('Keenable API key is missing. Set KEENABLE_API_KEY or use public billing.');
            }

            $headers['X-API-Key'] = $key;
        }

        if ($mode === 'public') {
            $title = $this->config['app_title'] ?? '';

            if ($title === '') {
                throw new MissingAppIdentifierException('Keenable app title is required for keyless requests. Set KEENABLE_APP_TITLE.');
            }

            if (mb_strlen($title) > 256) {
                throw new InvalidArgumentException('Keenable app title must not exceed 256 characters.');
            }

            $headers['X-Keenable-Title'] = $title;
        }

        return $headers;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function resolveBilling(string $billing): string
    {
        if ($billing === 'auto' || $billing === '') {
            return ($this->config['api_key'] ?? '') !== '' ? 'key' : 'public';
        }

        if (in_array($billing, ['key', 'public', 'x402'], true)) {
            return $billing;
        }

        throw new InvalidArgumentException("Invalid billing mode [$billing]. Expected 'auto', 'key', 'public' or 'x402'.");
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function path(string $endpoint): string
    {
        $billing = $this->resolveBilling($this->config['billing'] ?? 'auto');

        return match ($billing) {
            'public' => "/v1/$endpoint/public",
            'x402' => "/v1/x402/$endpoint",
            default => "/v1/$endpoint",
        };
    }

    /**
     * @throws AuthenticationException
     * @throws ForbiddenException
     * @throws KeenableException
     * @throws NoCreditsException
     * @throws RateLimitExceededException
     * @throws ServerException
     * @throws ValidationException
     */
    protected function throwForStatus(Response $response): never
    {
        $status = $response->status();
        $decoded = $response->json();
        $body = is_array($decoded) ? $decoded : [];
        $message = $this->errorMessage($response, $body, $status);
        $headers = $response->headers();

        throw match (true) {
            $status === 429 => new RateLimitExceededException($message, $status, $body, $headers),
            $status === 402 => new NoCreditsException($message, $status, $body, $headers),
            $status === 403 => new ForbiddenException($message, $status, $body, $headers),
            $status === 401 => new AuthenticationException($message, $status, $body, $headers),
            $status >= 400 && $status < 500 => new ValidationException($message, $status, $body, $headers),
            $status >= 500 => new ServerException($message, $status, $body, $headers),
            default => new KeenableException($message, $status, $body, $headers),
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function errorMessage(Response $response, array $body, int $status): string
    {
        foreach (['message', 'error', 'detail'] as $key) {
            if (isset($body[$key]) && is_string($body[$key]) && $body[$key] !== '') {
                return $body[$key];
            }
        }

        $raw = trim($response->body());

        return $raw !== '' ? $raw : "Keenable API request failed with status $status.";
    }
}
