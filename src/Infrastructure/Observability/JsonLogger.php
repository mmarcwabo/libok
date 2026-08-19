<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Observability;

use Psr\Log\AbstractLogger;
use Stringable;

final class JsonLogger extends AbstractLogger
{
    /** @var resource|null */
    private $stream = null;

    public function __construct(
        private readonly RequestContext $requestContext,
        private readonly ContextSanitizer $sanitizer,
        private readonly string $channel = 'application',
        private readonly ?string $destination = null,
    ) {
    }

    /** @param mixed $level */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $record = [
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            'level' => strtoupper((string) $level),
            'channel' => $this->channel,
            'message' => $this->interpolate((string) $message, $context),
            ...$this->requestContext->asLogContext(),
            'context' => $this->sanitizer->sanitize($context),
        ];
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"level":"ERROR","message":"Log record encoding failed"}';
        }
        $stream = $this->stream();
        if (flock($stream, LOCK_EX)) {
            fwrite($stream, $json . PHP_EOL);
            fflush($stream);
            flock($stream, LOCK_UN);
        }
    }

    /** @param array<string, mixed> $context */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof Stringable) {
                $sanitized = $this->sanitizer->sanitize((string) $value, (string) $key);
                $replace['{' . $key . '}'] = is_string($sanitized) ? $sanitized : '[REDACTED]';
            }
        }

        return strtr($message, $replace);
    }

    /** @return resource */
    private function stream()
    {
        if (is_resource($this->stream)) {
            return $this->stream;
        }
        $target = $this->destination ?? ($_ENV['LOG_DESTINATION'] ?? 'php://stderr');
        $stream = @fopen($target, 'ab');
        if ($stream === false) {
            $stream = fopen('php://stderr', 'ab');
        }
        if ($stream === false) {
            throw new \RuntimeException('Unable to open logging destination.');
        }

        return $this->stream = $stream;
    }
}
