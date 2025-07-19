<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCases;

use Libok\Application\UseCases\RegisterUserUseCase;
use Libok\Domain\Entities\User;
use Libok\Domain\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RegisterUserUseCaseTest extends TestCase
{
    public function test_can_register_a_new_user()
    {
        // Arrange
        $repositoryMock = $this->createMock(UserRepositoryInterface::class);
        $repositoryMock->method('findByEmail')->willReturn(null);
        $repositoryMock->expects($this->once())->method('save');

        $useCase = new RegisterUserUseCase($repositoryMock);

        // Act
        $user = $useCase->execute('John Doe', 'john@example.com', 'password123');

        // Assert
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->getName());
        $this->assertTrue(password_verify('password123', $user->getPassword()));
    }
}