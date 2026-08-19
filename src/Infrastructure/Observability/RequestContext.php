<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Observability;

final class RequestContext
{
    private ?string $requestId = null;
    private ?string $correlationId = null;

    public function set(string $requestId, string $correlationId): void
    {
        $this->requestId = $requestId;
        $this->correlationId = $correlationId;
    }

    /** @return array{request_id?: string, correlation_id?: string} */
    public function asLogContext(): array
    {
        $context = [];
        if ($this->requestId !== null) {
            $context['request_id'] = $this->requestId;
        }
        if ($this->correlationId !== null) {
            $context['correlation_id'] = $this->correlationId;
        }

        return $context;
    }
}
