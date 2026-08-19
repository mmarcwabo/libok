<?php

declare(strict_types=1);

namespace Tests\Support;

use Libok\Application\Contracts\MailerInterface;

final class RecordingMailer implements MailerInterface
{
    /** @var list<array{method: string, to: string, name: string}> */
    public array $welcome = [];

    /** @var list<array{to: string, toName: string, subject: string}> */
    public array $sent = [];

    public function send(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $this->sent[] = ['to' => $to, 'toName' => $toName, 'subject' => $subject];
    }

    public function sendNow(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $this->sent[] = ['to' => $to, 'toName' => $toName, 'subject' => $subject];
    }

    public function sendWelcome(string $to, string $name): void
    {
        $this->sendWelcomeNow($to, $name);
    }

    public function sendWelcomeNow(string $to, string $name): void
    {
        $this->welcome[] = ['method' => 'sendWelcomeNow', 'to' => $to, 'name' => $name];
    }
}
