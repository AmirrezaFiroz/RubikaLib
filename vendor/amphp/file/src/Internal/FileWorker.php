<?php declare(strict_types=1);

namespace Amp\File\Internal;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;

/** @internal */
final class FileWorker
{
    /**
     * @param \Closure(Worker):void $push Closure to push the worker back into the queue.
     */
    public function __construct(
        private readonly Worker $worker,
        private readonly \Closure $push
    ) {
    }

    /**
     * Automatically pushes the worker back into the queue.
     */
    public function __destruct()
    {
        ($this->push)($this->worker);
    }

    public function isRunning(): bool
    {
        return $this->worker->isRunning();
    }

    public function isIdle(): bool
    {
        return $this->worker->isIdle();
    }

    public function execute(Task $task, ?Cancellation $cancellation = null): mixed
    {
        return $this->worker->submit($task, $cancellation)->await();
    }

    public function shutdown(): void
    {
        $this->worker->shutdown();
    }

    public function kill(): void
    {
        $this->worker->kill();
    }
}
