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

class KeenableFetchTool implements Tool
{
    public function __construct(
        protected KeenableClient $client,
        protected bool $wrapExceptions = true,
    ) {
    }

    public function description(): Stringable|string
    {
        return 'Fetches a web page content from the internet and returns it as Markdown.';
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
            'max_chars' => $this->optionalInt($request, 'maxChars'),
            'live'      => $request->offsetExists('live') ? $request->boolean('live') : null,
        ], static fn ($value) => $value !== null);

        try {
            $page = $this->client->fetch(
                trim($request->string('url')->toString()),
                $options,
            );
        } catch (Throwable $exception) {
            if (! $this->wrapExceptions) {
                throw $exception;
            }

            report($exception);

            return '';
        }

        $title = $page->title !== null && $page->title !== '' ? "# $page->title\n\n" : '';

        return $title . ($page->content ?? 'Empty page');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url'      => $schema->string()->required()->description('HTTPS URL of the page to fetch'),
            'maxChars' => $schema->integer()->description('Maximum number of content characters'),
            'live'     => $schema->boolean()->description('Fetch the live page, including non-indexed URLs'),
        ];
    }

    protected function optionalInt(Request $request, string $key): ?int
    {
        $value = $request->integer($key);

        return $value === 0 ? null : $value;
    }
}
