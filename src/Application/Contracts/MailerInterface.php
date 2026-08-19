<?php

declare(strict_types=1);

namespace Libok\Application\Contracts;

interface MailerInterface
{
    public function send(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void;

    public function sendNow(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void;

    public function sendWelcome(string $to, string $name): void;

    public function sendWelcomeNow(string $to, string $name): void;
}
