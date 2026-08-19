<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Queue;

use Libok\Application\Contracts\JobHandlerInterface;
use Libok\Application\Contracts\JobQueueInterface;
use Psr\Log\LoggerInterface;

final class JobWorker
{
    /** @var array<string, JobHandlerInterface> */
    private array $handlers = [];

    /** @param iterable<JobHandlerInterface> $handlers */
    public function __construct(
        private readonly JobQueueInterface $queue,
        iterable $handlers,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($handlers as $handler) {
            $this->handlers[$handler->type()] = $handler;
        }
    }

    public function runOne(): bool
    {
        $job = $this->queue->claim();
        if ($job === null) {
            return false;
        }
        try {
            $handler = $this->handlers[$job['type']] ?? null;
            if ($handler === null) {
                throw new \RuntimeException('No handler registered for job type ' . $job['type']);
            }
            $handler->handle($job['payload']);
            $this->queue->complete($job['id']);
            $this->logger->info('Job completed', ['job_id' => $job['id'], 'job_type' => $job['type']]);
        } catch (\Throwable $error) {
            $this->queue->fail($job['id'], $job['attempts'], $job['max_attempts'], $error);
            $this->logger->error('Job failed', [
                'job_id' => $job['id'],
                'job_type' => $job['type'],
                'attempt' => $job['attempts'],
                'exception' => $error,
            ]);
        }

        return true;
    }

    public function work(int $maxJobs = 0, int $sleepMilliseconds = 1000): int
    {
        $processed = 0;
        while ($maxJobs === 0 || $processed < $maxJobs) {
            if (!$this->runOne()) {
                if ($maxJobs > 0) {
                    break;
                }
                usleep(max(10, $sleepMilliseconds) * 1000);
                continue;
            }
            ++$processed;
        }

        return $processed;
    }
}
