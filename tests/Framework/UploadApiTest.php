<?php

declare(strict_types=1);

namespace Tests\Framework;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\AuditLog;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadApiTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
    }

    public function testRejectsParentDirectoryTraversal(): void
    {
        $cookies = $this->loginMember();
        $file = $this->uploadedFile('hello kernel', 'note.txt');

        $response = $this->kernelRequest(
            'POST',
            '/api/v1/uploads',
            [],
            $cookies,
            ['directory' => '../secret'],
            ['file' => $file],
        );

        self::assertSame(422, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertFalse($payload['success']);
        self::assertSame('upload.rejected', $payload['code']);
    }

    public function testRejectsDisallowedMime(): void
    {
        $cookies = $this->loginMember();
        $file = $this->uploadedFile('<?php echo 1;', 'shell.php');

        $response = $this->kernelRequest(
            'POST',
            '/api/v1/uploads',
            [],
            $cookies,
            ['directory' => 'uploads'],
            ['file' => $file],
        );

        self::assertSame(422, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertSame('upload.rejected', $payload['code']);
    }

    public function testStoresAllowedUploadAndWritesAudit(): void
    {
        $cookies = $this->loginMember();
        $file = $this->uploadedFile('hello kernel', 'note.txt');

        $response = $this->kernelRequest(
            'POST',
            '/api/v1/uploads',
            [],
            $cookies,
            ['directory' => 'uploads'],
            ['file' => $file],
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decode((string) $response->getContent());
        self::assertTrue($payload['success']);
        self::assertStringStartsWith('uploads/', $payload['data']['path']);
        self::assertSame('/storage/' . $payload['data']['path'], $payload['data']['url']);

        $container = require dirname(__DIR__, 2) . '/config/services.php';
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $logs = $entityManager->getRepository(AuditLog::class)->findAll();
        self::assertNotEmpty($logs);
        $actions = array_map(static fn (AuditLog $log): string => $log->getAction(), $logs);
        self::assertContains('api.uploads.create', $actions);
    }

    /** @return array<string, string> */
    private function loginMember(): array
    {
        $this->jsonRequest('POST', '/api/v1/auth/register', [], [], [
            'name' => 'Uploader',
            'email' => 'upload@example.test',
            'password' => 'password123',
        ]);
        $login = $this->jsonRequest('POST', '/api/v1/auth/login', [], [], [
            'email' => 'upload@example.test',
            'password' => 'password123',
        ]);

        return $this->cookiesFrom($login);
    }

    private function uploadedFile(string $contents, string $clientName): UploadedFile
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'libok-upload-' . bin2hex(random_bytes(6));
        self::assertNotFalse(file_put_contents($path, $contents));

        return new UploadedFile($path, $clientName, null, null, true);
    }
}
