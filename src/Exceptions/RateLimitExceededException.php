<?php

namespace SubscopeAi\Keenable\Exceptions;

class RateLimitExceededException extends KeenableException
{
    public function retryAfter(): ?int
    {
        $value = $this->responseHeaders['Retry-After'][0]
            ?? $this->responseHeaders['retry-after'][0]
            ?? null;

        return $value !== null ? (int) $value : null;
    }

    public function rateLimit(): ?int
    {
        return $this->headerInt('X-RateLimit-Limit');
    }

    public function rateLimitRemaining(): ?int
    {
        return $this->headerInt('X-RateLimit-Remaining');
    }

    public function rateLimitReset(): ?int
    {
        return $this->headerInt('X-RateLimit-Reset');
    }

    protected function headerInt(string $name): ?int
    {
        foreach ($this->responseHeaders as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                $value = is_array($values) ? ($values[0] ?? null) : $values;

                return $value !== null ? (int) $value : null;
            }
        }

        return null;
    }
}
