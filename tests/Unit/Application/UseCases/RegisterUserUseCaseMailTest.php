<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCases;

use Libok\Application\Contracts\OutboxWriterInterface;
use Libok\Application\UseCases\RegisterUserUseCase;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\ThrowingMailer;

final class RegisterUserUseCaseMailTest extends TestCase
{
    public function testMailSyncFailureDoesNotPreventRegistration(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $previous = $_ENV['MAIL_SYNC'] ?? null;
        $_ENV['MAIL_SYNC'] = 'true';
        try {
            $useCase = new RegisterUserUseCase($repository, mailer: new ThrowingMailer());
            $user = $useCase->execute('Ada Lovelace', 'ada-mail@example.test', 'password123');
        } finally {
            if ($previous === null) {
                unset($_ENV['MAIL_SYNC']);
            } else {
                $_ENV['MAIL_SYNC'] = $previous;
            }
        }

        self::assertInstanceOf(User::class, $user);
        self::assertSame('ada-mail@example.test', $user->getEmail());
    }

    public function testOutboxPayloadDoesNotIncludePassword(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $outbox = $this->createMock(OutboxWriterInterface::class);
        $outbox->expects(self::once())
            ->method('append')
            ->with(
                'user.registered',
                self::callback(static function (array $payload): bool {
                    return isset($payload['user_id'], $payload['email'], $payload['name'])
                        && !array_key_exists('password', $payload)
                        && $payload['email'] === 'outbox@example.test';
                }),
                self::isType('string'),
            );

        $useCase = new RegisterUserUseCase($repository, outboxWriter: $outbox);
        $useCase->execute('Outbox User', 'outbox@example.test', 'password123');
    }
}
