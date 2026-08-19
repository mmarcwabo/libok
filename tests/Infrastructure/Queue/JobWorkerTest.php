<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Queue;

use Libok\Application\Contracts\JobHandlerInterface;
use Libok\Infrastructure\Queue\FilesystemJobQueue;
use Libok\Infrastructure\Queue\JobWorker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class JobWorkerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'libok-worker-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function testRunOneCompletesHandledJob(): void
    {
        $queue = new FilesystemJobQueue($this->directory);
        $queue->dispatch('test.ping', ['ok' => true]);
        $handler = new class () implements JobHandlerInterface {
            /** @var list<array<string, mixed>> */
            public array $handled = [];

            public function type(): string
            {
                return 'test.ping';
            }

            public function handle(array $payload): void
            {
                $this->handled[] = $payload;
            }
        };
        $worker = new JobWorker($queue, [$handler], new NullLogger());
        self::assertTrue($worker->runOne());
        self::assertSame([['ok' => true]], $handler->handled);
        self::assertFalse($worker->runOne());
    }
}
