<?php

declare(strict_types=1);

namespace Libok\Infrastructure\Queue;

use Libok\Application\Contracts\JobQueueInterface;

final class KernelWorker
{
    public function __construct(
        private readonly OutboxPublisher $outboxPublisher,
        private readonly JobWorker $jobWorker,
        private readonly JobQueueInterface $queue,
    ) {
    }

    public function runOnce(): int
    {
        $this->queue->releaseStale();
        $processed = 0;
        while ($this->outboxPublisher->publishOne()) {
            ++$processed;
        }
        while ($this->jobWorker->runOne()) {
            ++$processed;
        }

        return $processed;
    }

    public function work(int $sleepMilliseconds = 1000): never
    {
        while (true) {
            if ($this->runOnce() === 0) {
                usleep(max(10, $sleepMilliseconds) * 1000);
            }
        }
    }
}
