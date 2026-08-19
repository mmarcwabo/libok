<?php

declare(strict_types=1);

namespace Tests\Support;

use Libok\Application\Contracts\MailerInterface;

final class ThrowingMailer implements MailerInterface
{
    public function send(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        throw new \RuntimeException('SMTP unavailable.');
    }

    public function sendNow(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        throw new \RuntimeException('SMTP unavailable.');
    }

    public function sendWelcome(string $to, string $name): void
    {
        throw new \RuntimeException('SMTP unavailable.');
    }

    public function sendWelcomeNow(string $to, string $name): void
    {
        throw new \RuntimeException('SMTP unavailable.');
    }
}
