<?php

declare(strict_types=1);

namespace Libok\Application\UseCases;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Application\Contracts\MailerInterface;
use Libok\Application\Contracts\OutboxWriterInterface;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;
use Libok\Infrastructure\Services\PasswordService;
use Psr\Log\LoggerInterface;

class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordService $passwordService = new PasswordService(),
        private readonly ?OutboxWriterInterface $outboxWriter = null,
        private readonly ?MailerInterface $mailer = null,
        private readonly ?EntityManagerInterface $entityManager = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function execute(string $name, string $email, string $password): User
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || strlen($name) > 255) {
            throw new \InvalidArgumentException('Name is required.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new \InvalidArgumentException('A valid email is required.');
        }
        if ($this->userRepository->findByEmail($email)) {
            throw new \InvalidArgumentException('User with this email already exists.');
        }

        $user = new User($name, $email, $this->passwordService->hash($password));
        $persist = function () use ($user): void {
            $this->userRepository->save($user);
            if ($this->outboxWriter === null) {
                return;
            }
            $this->outboxWriter->append(
                'user.registered',
                [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                ],
                $user->getId(),
            );
        };

        if ($this->entityManager !== null) {
            $this->entityManager->wrapInTransaction($persist);
        } else {
            $persist();
        }

        $this->maybeSendWelcomeSynchronously($user);

        return $user;
    }

    private function maybeSendWelcomeSynchronously(User $user): void
    {
        if ($this->mailer === null) {
            return;
        }
        if (!filter_var($_ENV['MAIL_SYNC'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        try {
            $this->mailer->sendWelcomeNow($user->getEmail(), $user->getName());
        } catch (\Throwable $error) {
            $this->logger?->warning('Welcome email failed after register.', [
                'user_id' => $user->getId(),
                'exception' => $error,
            ]);
        }
    }
}
