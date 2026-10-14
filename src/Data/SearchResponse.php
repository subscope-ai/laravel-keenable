<?php

namespace SubscopeAi\Keenable\Data;

final class SearchResponse
{
    /**
     * @param  array<SearchResult>  $results
     */
    public function __construct(
        public readonly string $query,
        public readonly ?string $mode,
        public readonly array $results,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['query'] ?? '',
            $data['mode'] ?? null,
            array_map(
                static fn (array $item) => SearchResult::fromArray($item),
                $data['results'] ?? []
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'mode' => $this->mode,
            'results' => array_map(
                static fn (SearchResult $result) => $result->toArray(),
                $this->results
            ),
        ];
    }
}
