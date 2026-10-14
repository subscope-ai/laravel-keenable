<?php

namespace SubscopeAi\Keenable\Data;

final class FetchResponse
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $author = null,
        public readonly ?string $content = null,
        public readonly ?int $publishedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            author: $data['author'] ?? null,
            content: $data['content'] ?? null,
            publishedAt: $data['published_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'description' => $this->description,
            'author' => $this->author,
            'content' => $this->content,
            'published_at' => $this->publishedAt,
        ];
    }
}
