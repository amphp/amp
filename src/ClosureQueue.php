<?php

namespace Amp;

use Revolt\EventLoop;

final class ClosureQueue
{
    /** @var list<\Closure():void>|null */
    private ?array $closures = [];

    public function __destruct()
    {
        if ($this->closures !== null) {
            $this->call();
        }
    }

    /**
     * @param \Closure():void $onClose
     */
    public function push(\Closure $closure): void
    {
        if ($this->closures === null) {
            EventLoop::queue($closure);
            return;
        }

        $this->closures[] = $closure;
    }

    public function call(): void
    {
        if ($this->closures === null) {
            return;
        }

        foreach ($this->closures as $closure) {
            EventLoop::queue($closure);
        }

        $this->closures = null;
    }
}
