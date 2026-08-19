<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Queue;

use Libok\Application\Contracts\JobHandlerInterface;
use Libok\Application\Contracts\MailerInterface;

final class EmailJobHandler implements JobHandlerInterface
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function type(): string
    {
        return 'email.send';
    }

    public function handle(array $payload): void
    {
        foreach (['to', 'to_name', 'subject', 'html'] as $required) {
            if (!isset($payload[$required]) || !is_string($payload[$required])) {
                throw new \InvalidArgumentException("Invalid email job field: {$required}");
            }
        }
        $this->mailer->sendNow(
            $payload['to'],
            $payload['to_name'],
            $payload['subject'],
            $payload['html'],
            isset($payload['text']) && is_string($payload['text']) ? $payload['text'] : null,
        );
    }
}
