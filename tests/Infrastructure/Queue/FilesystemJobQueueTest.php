<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Queue;

use Libok\Infrastructure\Queue\FilesystemJobQueue;
use PHPUnit\Framework\TestCase;

final class FilesystemJobQueueTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'libok-queue-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
        parent::tearDown();
    }

    public function testDispatchClaimComplete(): void
    {
        $queue = new FilesystemJobQueue($this->directory);
        $id = $queue->dispatch('email.send', ['to' => 'ada@example.test']);
        $job = $queue->claim();
        self::assertNotNull($job);
        self::assertSame($id, $job['id']);
        self::assertSame('email.send', $job['type']);
        self::assertSame(['to' => 'ada@example.test'], $job['payload']);
        self::assertSame(1, $job['attempts']);
        $queue->complete($id);
        self::assertNull($queue->claim());
        self::assertFileExists($this->directory . DIRECTORY_SEPARATOR . 'completed' . DIRECTORY_SEPARATOR . $id . '.json');
    }

    public function testFailRetriesThenMovesToDead(): void
    {
        $queue = new FilesystemJobQueue($this->directory);
        $id = $queue->dispatch('email.send', ['to' => 'ada@example.test'], maxAttempts: 2);
        $first = $queue->claim();
        self::assertNotNull($first);
        $queue->fail($id, $first['attempts'], $first['max_attempts'], new \RuntimeException('temporary'));
        $pending = glob($this->directory . DIRECTORY_SEPARATOR . 'pending' . DIRECTORY_SEPARATOR . '*.json') ?: [];
        self::assertNotSame([], $pending);
        $record = json_decode((string) file_get_contents($pending[0]), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        $record['available_at'] = (new \DateTimeImmutable('-1 second'))->format(\DateTimeInterface::ATOM);
        file_put_contents($pending[0], json_encode($record, JSON_THROW_ON_ERROR));
        $second = $queue->claim();
        self::assertNotNull($second);
        self::assertSame(2, $second['attempts']);
        $queue->fail($id, $second['attempts'], $second['max_attempts'], new \RuntimeException('permanent'));
        self::assertNull($queue->claim());
        self::assertFileExists($this->directory . DIRECTORY_SEPARATOR . 'dead' . DIRECTORY_SEPARATOR . $id . '.json');
    }

    public function testIdempotencyKeyReturnsTheSameJobId(): void
    {
        $queue = new FilesystemJobQueue($this->directory);
        $first = $queue->dispatch('email.send', ['n' => 1], 'welcome-ada');
        $second = $queue->dispatch('email.send', ['n' => 2], 'welcome-ada');
        self::assertSame($first, $second);
    }

    public function testReleaseStaleReturnsAbandonedJobs(): void
    {
        $queue = new FilesystemJobQueue($this->directory);
        $id = $queue->dispatch('email.send', ['to' => 'ada@example.test']);
        $job = $queue->claim();
        self::assertNotNull($job);
        $processing = $this->directory . DIRECTORY_SEPARATOR . 'processing' . DIRECTORY_SEPARATOR . $id . '.json';
        $record = json_decode((string) file_get_contents($processing), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        $record['reserved_at'] = (new \DateTimeImmutable('-1 hour'))->format(\DateTimeInterface::ATOM);
        file_put_contents($processing, json_encode($record, JSON_THROW_ON_ERROR));
        self::assertSame(1, $queue->releaseStale(60));
        $reclaimed = $queue->claim();
        self::assertNotNull($reclaimed);
        self::assertSame($id, $reclaimed['id']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($directory);
    }
}
