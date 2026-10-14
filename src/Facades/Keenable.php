<?php

namespace SubscopeAi\Keenable\Facades;

use Illuminate\Support\Facades\Facade;
use SubscopeAi\Keenable\Data\FetchResponse;
use SubscopeAi\Keenable\Data\SearchResponse;
use SubscopeAi\Keenable\Enums\SearchMode;

/**
 * @method static SearchResponse search(string $query, array{mode?: SearchMode|string, site?: string, acquired_after?: string, acquired_before?: string, published_after?: string, published_before?: string, query_time?: string, snippet_max_length?: int, max_results?: int} $options = [])
 * @method static FetchResponse fetch(string $url, array{max_chars?: int, live?: bool, prompt?: string} $options = [])
 *
 * @see \SubscopeAi\Keenable\KeenableClient
 */
class Keenable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'keenable';
    }
}
