<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Mail;

use Libok\Application\Contracts\JobQueueInterface;
use Libok\Application\Contracts\MailerInterface;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

final class MailerService implements MailerInterface
{
    public function __construct(
        private readonly ?JobQueueInterface $queue = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function send(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $queue = $this->queue;
        if ($queue !== null && $this->shouldQueue()) {
            $queue->dispatch('email.send', [
                'to' => $to,
                'to_name' => $toName,
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => $textBody,
            ]);

            return;
        }

        $this->sendNow($to, $toName, $subject, $htmlBody, $textBody);
    }

    public function sendNow(string $to, string $toName, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        try {
            $mailer = $this->buildMailer();
            $mailer->addAddress($to, $toName);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = $textBody ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            $mailer->send();
        } catch (PHPMailerException $e) {
            $this->logger?->error('Failed to send email.', ['to' => $to, 'exception' => $e]);
            throw new \RuntimeException('Failed to send email.', 0, $e);
        }
    }

    public function sendWelcome(string $to, string $name): void
    {
        $this->sendWelcomeNow($to, $name);
    }

    public function sendWelcomeNow(string $to, string $name): void
    {
        $appName = $this->appName();
        $appUrl = $this->appUrl();
        $subject = 'Welcome to ' . $appName;

        $body = $this->template($subject, $appName, $appUrl, 'Welcome', <<<BODY
            <p style="margin:0 0 16px">Hello <strong>{$this->e($name)}</strong>,</p>
            <p style="margin:0 0 16px">
                Your account on <strong>{$this->e($appName)}</strong> is ready.
                You can sign in with this email address:
            </p>
            {$this->infoBox([
                'Email' => $this->e($to),
            ])}
            {$this->button('Sign in', $appUrl !== '' ? $appUrl : '#', '#4f46e5')}
        BODY);

        $this->sendNow($to, $name, $subject, $body);
    }

    public function template(string $preview, string $appName, string $appUrl, string $heading, string $content): string
    {
        $escapedName = $this->e($appName);
        $escapedUrl = $this->e($appUrl);
        $escapedPreview = $this->e($preview);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title>{$escapedPreview}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">{$escapedPreview}&nbsp;&#847;&nbsp;</div>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">
      <tr>
        <td style="background:#4f46e5;border-radius:16px 16px 0 0;padding:28px 40px;text-align:center">
          <p style="margin:0;font-size:22px;font-weight:900;color:#ffffff;letter-spacing:-0.5px">{$escapedName}</p>
        </td>
      </tr>
      <tr>
        <td style="background:#ffffff;padding:40px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0">
          <h1 style="margin:0 0 24px;font-size:22px;font-weight:900;color:#0f172a;letter-spacing:-0.5px">{$heading}</h1>
          {$content}
        </td>
      </tr>
      <tr>
        <td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center">
          <p style="margin:0 0 6px;font-size:12px;color:#94a3b8">
            This email was sent automatically by <strong>{$escapedName}</strong>. Please do not reply.
          </p>
          <p style="margin:0;font-size:12px;color:#cbd5e1">
            <a href="{$escapedUrl}" style="color:#4f46e5;text-decoration:none">{$escapedUrl}</a>
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    private function shouldQueue(): bool
    {
        return !filter_var($_ENV['MAIL_SYNC'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function buildMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string) ($_ENV['MAIL_HOST'] ?? 'localhost');
        $mailer->Port = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $mailer->Timeout = 8;
        $encryption = (string) ($_ENV['MAIL_ENCRYPTION'] ?? '');
        $username = (string) ($_ENV['MAIL_USER'] ?? $_ENV['MAIL_USERNAME'] ?? '');
        if ($username !== '' && $encryption !== '') {
            $mailer->SMTPAuth = true;
            $mailer->Username = $username;
            $mailer->Password = (string) ($_ENV['MAIL_PASS'] ?? $_ENV['MAIL_PASSWORD'] ?? '');
            $mailer->SMTPSecure = $encryption;
        } else {
            $mailer->SMTPAuth = false;
            $mailer->SMTPSecure = '';
        }
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom(
            (string) ($_ENV['MAIL_FROM'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@localhost'),
            (string) ($_ENV['MAIL_FROM_NAME'] ?? $this->appName()),
        );

        return $mailer;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function appName(): string
    {
        return (string) ($_ENV['APP_NAME'] ?? $_ENV['MAIL_FROM_NAME'] ?? 'Libok');
    }

    private function appUrl(): string
    {
        return rtrim((string) ($_ENV['FRONTEND_URL'] ?? $_ENV['APP_URL'] ?? ''), '/');
    }

    private function button(string $label, string $url, string $color = '#4f46e5'): string
    {
        $eUrl = $this->e($url);
        $eLabel = $this->e($label);

        return <<<HTML
        <table cellpadding="0" cellspacing="0" style="margin:24px 0">
          <tr>
            <td style="background:{$color};border-radius:10px">
              <a href="{$eUrl}" style="display:inline-block;padding:14px 28px;color:#ffffff;font-weight:700;font-size:14px;text-decoration:none;letter-spacing:0.2px">{$eLabel}</a>
            </td>
          </tr>
        </table>
        HTML;
    }

    /**
     * @param array<string, string> $rows
     */
    private function infoBox(array $rows): string
    {
        $html = '<table cellpadding="0" cellspacing="0" width="100%" style="margin:16px 0;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden">';
        $first = true;
        foreach ($rows as $label => $value) {
            $border = $first ? '' : 'border-top:1px solid #e2e8f0;';
            $html .= <<<ROW
            <tr>
              <td style="{$border}padding:10px 16px;background:#f8fafc;font-size:12px;font-weight:700;color:#64748b;white-space:nowrap;width:40%">{$label}</td>
              <td style="{$border}padding:10px 16px;background:#ffffff;font-size:14px;color:#1e293b">{$value}</td>
            </tr>
            ROW;
            $first = false;
        }
        $html .= '</table>';

        return $html;
    }
}
