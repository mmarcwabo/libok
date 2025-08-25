<?php

declare(strict_types=1);

namespace Tests\Integration\Application\UseCases;

use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Libok\Application\UseCases\RegisterUserUseCase;
use Libok\Domain\Entities\User;
use Libok\Infrastructure\Persistence\Repositories\DoctrineUserRepository;
use PHPUnit\Framework\TestCase;

class RegisterUserUseCaseIntegrationTest extends TestCase
{
    private EntityManager $entityManager;
    private DoctrineUserRepository $repository;

    protected function setUp(): void
    {
        // Setup Doctrine with in-memory SQLite
        $config = Setup::createAttributeMetadataConfiguration(
            [__DIR__ . '/../../../../src/Domain/Entities'], // path to your entities
            true, // dev mode
        );

        $connection = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $this->entityManager = EntityManager::create($connection, $config);
        $this->repository = new DoctrineUserRepository($this->entityManager);

        // Create schema for User entity
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);
    }

    public function test_register_user_persists_in_database()
    {
        // Arrange
        $useCase = new RegisterUserUseCase($this->repository);

        // Act
        $user = $useCase->execute('John Doe', 'john@example.com', 'password123');

        // Assert: returned object is a User
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->getName());

        // Assert: user is persisted in DB
        $persistedUser = $this->repository->findByEmail('john@example.com');
        $this->assertNotNull($persistedUser);
        $this->assertEquals('John Doe', $persistedUser->getName());
        $this->assertTrue(password_verify('password123', $persistedUser->getPassword()));
    }
}
