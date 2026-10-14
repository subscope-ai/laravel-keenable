<?php

namespace SubscopeAi\Keenable\Exceptions;

use Exception;

class KeenableException extends Exception
{
    /**
     * @param  array<string, mixed>  $responseBody
     * @param  array<string, mixed>  $responseHeaders
     */
    public function __construct(
        string $message = '',
        protected int $statusCode = 0,
        protected array $responseBody = [],
        protected array $responseHeaders = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function responseBody(): array
    {
        return $this->responseBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
