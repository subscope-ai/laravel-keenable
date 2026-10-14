<?php

use Illuminate\Support\Facades\Http;
use SubscopeAi\Keenable\Data\FetchResponse;
use SubscopeAi\Keenable\Data\SearchResponse;
use SubscopeAi\Keenable\Exceptions\AuthenticationException;
use SubscopeAi\Keenable\Exceptions\NoCreditsException;
use SubscopeAi\Keenable\Exceptions\RateLimitExceededException;
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

it('maps 402 to NoCreditsException', function () {
    Http::fake(['*' => Http::response(['message' => 'No credits'], 402)]);

    expect(fn () => Keenable::search('test'))->toThrow(NoCreditsException::class);
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
        'Retry-After' => '30',
        'X-RateLimit-Remaining' => '0',
    ])]);

    try {
        Keenable::search('test');
        $this->fail('Exception was not thrown.');
    } catch (RateLimitExceededException $e) {
        expect($e->retryAfter())->toBe(30)
            ->and($e->rateLimitRemaining())->toBe(0);
    }
});

it('validates max_results client-side', function () {
    expect(fn () => Keenable::search('test', ['max_results' => 99]))
        ->toThrow(InvalidArgumentException::class);
});
