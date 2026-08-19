<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Services;

use Libok\Infrastructure\Services\FileStorageService;
use Libok\Infrastructure\Storage\NullMalwareScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileStorageServiceTest extends TestCase
{
    private string $storageDirectory;
    private string|false $previousStoragePath;
    private string|false $previousPublicStoragePath;

    protected function setUp(): void
    {
        $this->storageDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'libok-storage-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->storageDirectory, 0700, true));
        self::assertTrue(mkdir($this->storageDirectory . DIRECTORY_SEPARATOR . 'public', 0700, true));
        $this->previousStoragePath = $_ENV['STORAGE_PATH'] ?? false;
        $this->previousPublicStoragePath = $_ENV['PUBLIC_STORAGE_PATH'] ?? false;
        $_ENV['STORAGE_PATH'] = $this->storageDirectory;
        $_ENV['PUBLIC_STORAGE_PATH'] = $this->storageDirectory . DIRECTORY_SEPARATOR . 'public';
    }

    protected function tearDown(): void
    {
        if ($this->previousStoragePath === false) {
            unset($_ENV['STORAGE_PATH']);
        } else {
            $_ENV['STORAGE_PATH'] = $this->previousStoragePath;
        }
        if ($this->previousPublicStoragePath === false) {
            unset($_ENV['PUBLIC_STORAGE_PATH']);
        } else {
            $_ENV['PUBLIC_STORAGE_PATH'] = $this->previousPublicStoragePath;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->storageDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->storageDirectory);
    }

    public function testStoresPublicAssetsUnderPublicStorageRoot(): void
    {
        $storage = new FileStorageService(new NullMalwareScanner());
        $storage->storeFromContent('avatar-bytes', 'avatars/brand.png');

        $fullPath = $storage->getFullPath('avatars/brand.png');
        self::assertStringContainsString(DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'avatars', $fullPath);
        self::assertSame('/storage/avatars/brand.png', $storage->publicUrl('avatars/brand.png'));
        self::assertSame('avatar-bytes', $storage->read('avatars/brand.png'));
    }

    public function testStoresAndReadsContentInsidePrivateRoot(): void
    {
        $storage = new FileStorageService(new NullMalwareScanner());
        $storage->storeFromContent('private document', 'private/owner/file.txt');

        self::assertSame('private document', $storage->read('private/owner/file.txt'));
        self::assertTrue($storage->exists('private\\owner\\file.txt'));
        self::assertNull($storage->publicUrl('private/owner/file.txt'));
        $resolvedRoot = realpath($this->storageDirectory);
        self::assertNotFalse($resolvedRoot);
        self::assertStringStartsWith($resolvedRoot, $storage->getFullPath('private/owner/file.txt'));
    }

    #[DataProvider('unsafePathProvider')]
    public function testRejectsPathsThatEscapePrivateRoot(string $path): void
    {
        $storage = new FileStorageService(new NullMalwareScanner());

        $this->expectException(\InvalidArgumentException::class);
        $storage->getFullPath($path);
    }

    /** @return iterable<string, array{string}> */
    public static function unsafePathProvider(): iterable
    {
        yield 'parent traversal' => ['../public/secret.txt'];
        yield 'nested traversal' => ['uploads/../../.env'];
        yield 'unix absolute path' => ['/etc/passwd'];
        yield 'windows absolute path' => ['C:\\Windows\\win.ini'];
        yield 'null byte' => ["uploads/file.txt\0.pdf"];
        yield 'empty path' => [''];
    }

    public function testRejectsDisallowedMimeAndExtension(): void
    {
        $storage = new FileStorageService(new NullMalwareScanner());
        $php = $this->tempUpload('<?php echo 1;', 'shell.php');

        $this->expectException(\InvalidArgumentException::class);
        $storage->store($php, 'uploads');
    }

    public function testRejectsExtensionMimeMismatch(): void
    {
        $storage = new FileStorageService(new NullMalwareScanner());
        $fakePng = $this->tempUpload('not-a-png', 'photo.png');

        $this->expectException(\InvalidArgumentException::class);
        $storage->store($fakePng, 'uploads');
    }

    public function testStoresAllowedTextFile(): void
    {
        $storage = new FileStorageService(new NullMalwareScanner());
        $file = $this->tempUpload('hello kernel', 'note.txt');
        $relative = $storage->store($file, 'uploads');

        self::assertStringStartsWith('uploads/', $relative);
        self::assertSame('hello kernel', $storage->read($relative));
        self::assertSame('/storage/' . $relative, $storage->publicUrl($relative));
    }

    private function tempUpload(string $contents, string $clientName): UploadedFile
    {
        $path = $this->storageDirectory . DIRECTORY_SEPARATOR . 'upload-' . bin2hex(random_bytes(4));
        self::assertNotFalse(file_put_contents($path, $contents));

        return new UploadedFile($path, $clientName, null, null, true);
    }
}
