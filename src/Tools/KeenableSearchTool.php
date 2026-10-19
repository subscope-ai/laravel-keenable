<?php

namespace SubscopeAi\Keenable\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use SubscopeAi\Keenable\Exceptions\AuthenticationException;
use SubscopeAi\Keenable\Exceptions\ForbiddenException;
use SubscopeAi\Keenable\Exceptions\KeenableException;
use SubscopeAi\Keenable\Exceptions\MissingAppIdentifierException;
use SubscopeAi\Keenable\Exceptions\NoCreditsException;
use SubscopeAi\Keenable\Exceptions\RateLimitExceededException;
use SubscopeAi\Keenable\Exceptions\ServerException;
use SubscopeAi\Keenable\Exceptions\ValidationException;
use SubscopeAi\Keenable\KeenableClient;
use Throwable;

class KeenableSearchTool implements Tool
{
    public function __construct(
        protected KeenableClient $client,
        protected bool $wrapExceptions = true,
    ) {
    }

    public function description(): Stringable|string
    {
        return 'Searches the internet and returns ranked results with titles, URLs and snippets.';
    }

    /**
     * @throws KeenableException
     * @throws ForbiddenException
     * @throws AuthenticationException
     * @throws NoCreditsException
     * @throws ValidationException
     * @throws MissingAppIdentifierException
     * @throws RateLimitExceededException
     * @throws Throwable
     * @throws ConnectionException
     * @throws ServerException
     */
    public function handle(Request $request): Stringable|string
    {
        $options = array_filter([
            'site'        => $this->optionalString($request, 'site'),
            'mode'        => $this->optionalString($request, 'mode'),
            'max_results' => $this->optionalInt($request, 'maxResults'),
        ], static fn ($value) => $value !== null);

        try {
            $response = $this->client->search(
                trim($request->string('query')->toString()),
                $options,
            );
        } catch (Throwable $exception) {
            if (! $this->wrapExceptions) {
                throw $exception;
            }

            report($exception);

            return '[]';
        }

        $results = array_map(static fn ($hit): array => [
            'title'       => $hit->title,
            'url'         => $hit->url,
            'description' => $hit->description,
            'snippet'     => $hit->snippet,
            'publishedAt' => $hit->publishedAt,
        ], $response->results);

        return json_encode($results);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'      => $schema->string()->required()->description('Search query'),
            'site'       => $schema->string()->description('Restrict results to a single source'),
            'maxResults' => $schema->integer()->description('Maximum number of results, 1-50'),
            'mode'       => $schema->string()->description("Search mode: 'pro' or 'realtime'"),
        ];
    }

    protected function optionalString(Request $request, string $key): ?string
    {
        $value = trim($request->string($key)->toString());

        return $value === '' ? null : $value;
    }

    protected function optionalInt(Request $request, string $key): ?int
    {
        $value = $request->integer($key);

        return $value === 0 ? null : $value;
    }
}
