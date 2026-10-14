<?php

namespace SubscopeAi\Keenable\Data;

final class SearchResult
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $description = null,
        public readonly ?string $snippet = null,
        public readonly ?string $publishedAt = null,
        public readonly ?string $acquiredAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            url: $data['url'] ?? '',
            description: $data['description'] ?? null,
            snippet: $data['snippet'] ?? null,
            publishedAt: $data['published_at'] ?? null,
            acquiredAt: $data['acquired_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'description' => $this->description,
            'snippet' => $this->snippet,
            'published_at' => $this->publishedAt,
            'acquired_at' => $this->acquiredAt,
        ];
    }
}
