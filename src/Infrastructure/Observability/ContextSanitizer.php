<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Observability;

final class ContextSanitizer
{
    private const SENSITIVE_KEYS = [
        'authorization', 'cookie', 'set-cookie', 'password', 'passwd', 'secret',
        'token', 'access_token', 'refresh_token', 'api_key', 'apikey', 'private_key',
        'credit_card', 'card_number', 'cvv', 'db_password', 'mail_password',
    ];

    public function sanitize(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if ($key !== null && $this->isSensitive($key)) {
            return '[REDACTED]';
        }
        if ($depth >= 8) {
            return '[MAX_DEPTH]';
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitize($childValue, (string) $childKey, $depth + 1);
            }

            return $clean;
        }
        if ($value instanceof \Throwable) {
            return [
                'class' => $value::class,
                'message' => $this->redactText($value->getMessage()),
                'code' => $value->getCode(),
            ];
        }
        if (is_object($value)) {
            return ['class' => $value::class];
        }
        if (is_string($value)) {
            return $this->redactText(strlen($value) > 4096 ? substr($value, 0, 4096) . '[TRUNCATED]' : $value);
        }

        return $value;
    }

    private function isSensitive(string $key): bool
    {
        $normalised = strtolower(str_replace(['-', '.'], '_', $key));
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalised === $sensitive || str_ends_with($normalised, '_' . $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function redactText(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace('/([?&](?:token|secret|password|api_key)=)[^&\s]+/i', '$1[REDACTED]', $value) ?? $value;

        return $value;
    }
}
