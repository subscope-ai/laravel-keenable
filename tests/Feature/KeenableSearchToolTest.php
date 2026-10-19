<?php

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use SubscopeAi\Keenable\Data\FetchResponse;
use SubscopeAi\Keenable\Data\SearchResponse;
use SubscopeAi\Keenable\Exceptions\ServerException;
use SubscopeAi\Keenable\KeenableClient;
use SubscopeAi\Keenable\Tools\KeenableFetchTool;
use SubscopeAi\Keenable\Tools\KeenableSearchTool;

test('keenable search tool exposes query schema', function () {
    $tool = new KeenableSearchTool(mock(KeenableClient::class));

    expect($tool)
        ->toBeInstanceOf(Tool::class)
        ->and($tool->description())->not->toBeEmpty()
        ->and(array_keys($tool->schema(new JsonSchemaTypeFactory())))
        ->toEqualCanonicalizing(['query', 'site', 'maxResults', 'mode']);
});

test('keenable search tool returns mapped results', function () {
    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->once()
            ->with('example query', \Mockery::type('array'))
            ->andReturn(SearchResponse::fromArray([
                'query'   => 'example query',
                'mode'    => 'pro',
                'results' => [
                    [
                        'title'        => 'Example Result',
                        'url'          => 'https://example.test/result',
                        'description'  => 'Result description',
                        'snippet'      => 'Snippet text',
                        'published_at' => '2026-10-01',
                        'acquired_at'  => null,
                    ],
                ],
            ]));
    });

    $results = json_decode(
        (string) (new KeenableSearchTool($client))->handle(new Request([
            'query'      => 'example query',
            'maxResults' => 5,
        ])),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($results)->toHaveCount(1)
        ->and($results[0])->toMatchArray([
            'title' => 'Example Result',
            'url'   => 'https://example.test/result',
        ]);
});

test('keenable search tool wraps provider errors by default', function () {
    Exceptions::fake();

    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('search')->once()->andThrow(new ServerException('boom', 500));
    });

    $result = (string) (new KeenableSearchTool($client))->handle(new Request(['query' => 'example']));

    expect($result)->toBe('[]');
    Exceptions::assertReported(ServerException::class);
});

test('keenable search tool propagates provider errors when wrapping is disabled', function () {
    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('search')->once()->andThrow(new ServerException('boom', 500));
    });

    expect(fn () => (new KeenableSearchTool($client, false))->handle(new Request(['query' => 'example'])))
        ->toThrow(ServerException::class, 'boom');
});

test('keenable search tool receives the config when resolved from the container', function () {
    config()->set('keenable.wrap_tool_exceptions', false);

    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('search')->once()->andThrow(new ServerException('boom', 500));
    });
    $this->app->instance(KeenableClient::class, $client);

    $tool = $this->app->make(KeenableSearchTool::class);

    expect(fn () => $tool->handle(new Request(['query' => 'example'])))
        ->toThrow(ServerException::class, 'boom');
});

test('keenable fetch tool exposes url schema', function () {
    $tool = new KeenableFetchTool(mock(KeenableClient::class));

    expect($tool)
        ->toBeInstanceOf(Tool::class)
        ->and($tool->description())->not->toBeEmpty()
        ->and(array_keys($tool->schema(new JsonSchemaTypeFactory())))
        ->toEqualCanonicalizing(['url', 'maxChars', 'live']);
});

test('keenable fetch tool returns content', function () {
    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('fetch')
            ->once()
            ->andReturn(FetchResponse::fromArray([
                'url'     => 'https://example.test/resource',
                'title'   => 'Example Result',
                'content' => '# Content',
            ]));
    });

    $response = (string) (new KeenableFetchTool($client))->handle(new Request([
        'url' => 'https://example.test/resource',
    ]));

    expect($response)->toContain('Content');
});

test('keenable fetch tool wraps provider errors by default', function () {
    Exceptions::fake();

    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andThrow(new ServerException('boom', 500));
    });

    $result = (string) (new KeenableFetchTool($client))->handle(new Request(['url' => 'https://example.test']));

    expect($result)->toBe('');
    Exceptions::assertReported(ServerException::class);
});

test('keenable fetch tool propagates provider errors when wrapping is disabled', function () {
    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andThrow(new ServerException('boom', 500));
    });

    expect(fn () => (new KeenableFetchTool($client, false))->handle(new Request(['url' => 'https://example.test'])))
        ->toThrow(ServerException::class, 'boom');
});

test('keenable fetch tool receives the config when resolved from the container', function () {
    config()->set('keenable.wrap_tool_exceptions', false);

    $client = mock(KeenableClient::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andThrow(new ServerException('boom', 500));
    });
    $this->app->instance(KeenableClient::class, $client);

    $tool = $this->app->make(KeenableFetchTool::class);

    expect(fn () => $tool->handle(new Request(['url' => 'https://example.test'])))
        ->toThrow(ServerException::class, 'boom');
});
