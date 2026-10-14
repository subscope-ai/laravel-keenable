<?php

use Illuminate\Support\Facades\Http;
use SubscopeAi\Keenable\Data\FetchResponse;
use SubscopeAi\Keenable\Data\SearchResponse;
use SubscopeAi\Keenable\Data\SearchResult;
use SubscopeAi\Keenable\Exceptions\AuthenticationException;
use SubscopeAi\Keenable\Exceptions\ForbiddenException;
use SubscopeAi\Keenable\Exceptions\MissingAppIdentifierException;
use SubscopeAi\Keenable\Exceptions\NoCreditsException;
use SubscopeAi\Keenable\Exceptions\RateLimitExceededException;
use SubscopeAi\Keenable\Exceptions\ServerException;
use SubscopeAi\Keenable\Exceptions\ValidationException;
use SubscopeAi\Keenable\Facades\Keenable;
use SubscopeAi\Keenable\KeenableClient;

it('sends search to the keyed endpoint with X-API-Key', function () {
    Http::fake([
        'api.keenable.ai/v1/search' => Http::response([
            'query' => 'laravel best practices',
            'mode' => 'pro',
            'results' => [
                [
                    'title' => 'Laravel Best Practices 2026',
                    'url' => 'https://example.com/laravel-best-practices',
                    'description' => 'A guide.',
                    'snippet' => 'Use strict mode...',
                    'published_at' => '2026-01-15T10:30:00Z',
                    'acquired_at' => '2026-01-16T08:12:34Z',
                ],
            ],
        ]),
    ]);

    $response = Keenable::search('laravel best practices');

    expect($response)->toBeInstanceOf(SearchResponse::class)
        ->and($response->results)->toHaveCount(1)
        ->and($response->results[0]->url)->toBe('https://example.com/laravel-best-practices');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.keenable.ai/v1/search'
            && $request->header('X-API-Key')[0] === 'keen_test_key'
            && $request['query'] === 'laravel best practices'
            && $request['mode'] === 'pro';
    });
});

it('uses the public endpoint with app title when no key is set', function () {
    config()->set('keenable.api_key');

    Http::fake([
        'api.keenable.ai/v1/search/public' => Http::response([
            'query' => 'hello',
            'mode' => 'pro',
            'results' => [],
        ]),
    ]);

    app(KeenableClient::class)->search('hello');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/v1/search/public')
            && ($request->header('X-Keenable-Title')[0] ?? null) === 'Test App';
    });
});

it('sends fetch as GET with query parameters', function () {
    Http::fake([
        'api.keenable.ai/v1/fetch*' => Http::response([
            'url' => 'https://example.com/laravel-best-practices',
            'title' => 'Laravel Best Practices 2026',
            'content' => '# Hello',
        ]),
    ]);

    $response = Keenable::fetch('https://example.com/laravel-best-practices', ['live' => true]);

    expect($response)->toBeInstanceOf(FetchResponse::class)
        ->and($response->content)->toBe('# Hello');

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/v1/fetch')
            && $request['url'] === 'https://example.com/laravel-best-practices'
            && $request['live'] === true;
    });
});

it('sends live=false explicitly instead of dropping it', function () {
    Http::fake([
        'api.keenable.ai/v1/fetch*' => Http::response([
            'url' => 'https://example.com/laravel-best-practices',
            'content' => '# Hello',
        ]),
    ]);

    Keenable::fetch('https://example.com/laravel-best-practices', ['live' => false]);

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && $request['live'] === false;
    });
});

it('maps 401 to AuthenticationException', function () {
    Http::fake(['*' => Http::response(['message' => 'Invalid API key'], 401)]);

    expect(fn () => Keenable::search('test'))->toThrow(AuthenticationException::class);
});

it('maps 402 to NoCreditsException and exposes response context', function () {
    Http::fake(['*' => Http::response(['message' => 'No credits'], 402)]);

    try {
        Keenable::search('test');
        $this->fail('Exception was not thrown.');
    } catch (NoCreditsException $e) {
        expect($e->statusCode())->toBe(402)
            ->and($e->responseBody())->toBe(['message' => 'No credits'])
            ->and($e->responseHeaders())->toBeArray();
    }
});

it('maps 400 to ValidationException without guessing the reason', function () {
    Http::fake(['*' => Http::response(['message' => 'Something changed upstream'], 400)]);

    expect(fn () => Keenable::search('test'))->toThrow(ValidationException::class);
});

it('rejects an app title longer than 256 characters', function () {
    config()->set('keenable.api_key');
    config()->set('keenable.app_title', str_repeat('a', 257));

    expect(fn () => Keenable::search('test'))->toThrow(InvalidArgumentException::class);
});

it('maps 429 to RateLimitExceededException with headers', function () {
    Http::fake(['*' => Http::response('Rate limited', 429, [
        'retry-after' => '30',
        'X-RateLimit-Limit' => '100',
        'X-RateLimit-Remaining' => '0',
        'X-RateLimit-Reset' => '123',
    ])]);

    try {
        Keenable::search('test');
        $this->fail('Exception was not thrown.');
    } catch (RateLimitExceededException $e) {
        expect($e->retryAfter())->toBe(30)
            ->and($e->rateLimit())->toBe(100)
            ->and($e->rateLimitRemaining())->toBe(0)
            ->and($e->rateLimitReset())->toBe(123);
    }
});

it('validates max_results client-side', function () {
    expect(fn () => Keenable::search('test', ['max_results' => 99]))
        ->toThrow(InvalidArgumentException::class);
});

it('validates empty query client-side', function () {
    expect(fn () => Keenable::search(''))->toThrow(InvalidArgumentException::class);
});

it('returns null for missing rate limit headers', function () {
    Http::fake(['*' => Http::response('Rate limited', 429, ['Retry-After' => '5'])]);

    try {
        Keenable::search('test');
        $this->fail('Exception was not thrown.');
    } catch (RateLimitExceededException $e) {
        expect($e->retryAfter())->toBe(5)
            ->and($e->rateLimit())->toBeNull();
    }
});

it('validates search mode client-side', function () {
    expect(fn () => Keenable::search('test', ['mode' => 'nonsense']))
        ->toThrow(InvalidArgumentException::class);
});

it('validates snippet length client-side', function () {
    expect(fn () => Keenable::search('test', ['snippet_max_length' => 10]))
        ->toThrow(InvalidArgumentException::class);
});

it('validates fetch url client-side', function () {
    expect(fn () => Keenable::fetch('not-a-url'))->toThrow(InvalidArgumentException::class);
});

it('validates max_chars client-side', function () {
    expect(fn () => Keenable::fetch('https://example.com/article', ['max_chars' => 0]))
        ->toThrow(InvalidArgumentException::class);
});

it('validates prompt length client-side', function () {
    expect(fn () => Keenable::fetch('https://example.com/article', ['prompt' => str_repeat('p', 2001)]))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when api key is missing in key mode', function () {
    config()->set('keenable.billing', 'key');
    config()->set('keenable.api_key');

    expect(fn () => Keenable::search('test'))->toThrow(AuthenticationException::class);
});

it('throws when app title is missing in public mode', function () {
    config()->set('keenable.api_key');
    config()->set('keenable.app_title', '');

    expect(fn () => Keenable::search('test'))->toThrow(MissingAppIdentifierException::class);
});

it('validates billing mode', function () {
    config()->set('keenable.billing', 'nonsense');

    expect(fn () => Keenable::search('test'))->toThrow(InvalidArgumentException::class);
});

it('uses x402 paths when billing is x402', function () {
    config()->set('keenable.billing', 'x402');

    Http::fake([
        'api.keenable.ai/v1/x402/search' => Http::response([
            'query' => 'laravel',
            'mode' => 'pro',
            'results' => [],
        ]),
    ]);

    $response = Keenable::search('laravel');

    expect($response)->toBeInstanceOf(SearchResponse::class);

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/v1/x402/search');
    });
});

it('maps 403 to ForbiddenException with the server message', function () {
    Http::fake(['*' => Http::response(['error' => 'Key revoked'], 403)]);

    expect(fn () => Keenable::search('test'))
        ->toThrow(ForbiddenException::class, 'Key revoked');
});

it('falls back to a generic message on an empty error body', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect(fn () => Keenable::search('test'))
        ->toThrow(ServerException::class, 'Keenable API request failed with status 500');
});

it('round-trips DTOs through toArray', function () {
    $resultData = [
        'title' => 'Laravel Best Practices 2026',
        'url' => 'https://example.com/laravel-best-practices',
        'description' => 'A guide.',
        'snippet' => 'Use strict mode...',
        'published_at' => '2026-01-15T10:30:00Z',
        'acquired_at' => '2026-01-16T08:12:34Z',
    ];

    expect(SearchResult::fromArray($resultData)->toArray())->toBe($resultData);

    $search = SearchResponse::fromArray([
        'query' => 'laravel',
        'mode' => 'pro',
        'results' => [$resultData],
    ]);

    expect($search->toArray())->toBe([
        'query' => 'laravel',
        'mode' => 'pro',
        'results' => [$resultData],
    ]);

    expect(SearchResponse::fromArray(['query' => 'laravel', 'results' => []])->mode)
        ->toBeNull();

    $pageData = [
        'url' => 'https://example.com/laravel-best-practices',
        'title' => 'Laravel Best Practices 2026',
        'description' => 'A guide.',
        'author' => 'Jane Doe',
        'content' => '# Hello',
        'published_at' => 1768435200,
    ];

    $page = FetchResponse::fromArray($pageData);

    expect($page->toArray())->toBe($pageData)
        ->and($page->publishedAt)->toBe(1768435200);
});
