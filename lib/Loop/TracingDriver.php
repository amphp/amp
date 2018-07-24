<?php

namespace Amp\Loop;

use function Amp\Internal\formatStacktrace;

// TODO: Move into separate repository
class TracingDriver extends Driver
{
    private $driver;
    private $creationTraces = [];
    private $cancelTraces = [];

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    public function run(): void
    {
        $this->driver->run();
    }

    public function stop(): void
    {
        $this->driver->stop();
    }

    public function defer(callable $callback, $data = null): string
    {
        $id = $this->driver->defer($callback, $data);
        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        return $id;
    }

    public function delay(int $delay, callable $callback, $data = null): string
    {
        $id = $this->driver->delay($delay, $callback, $data);
        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        return $id;
    }

    public function repeat(int $interval, callable $callback, $data = null): string
    {
        $id = $this->driver->repeat($interval, $callback, $data);
        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        return $id;
    }

    public function onReadable($stream, callable $callback, $data = null): string
    {
        $id = $this->driver->onReadable($stream, $callback, $data);
        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        return $id;
    }

    public function onWritable($stream, callable $callback, $data = null): string
    {
        $id = $this->driver->onWritable($stream, $callback, $data);
        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        return $id;
    }

    public function onSignal(int $signo, callable $callback, $data = null): string
    {
        $id = $this->driver->onSignal($signo, $callback, $data);
        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        return $id;
    }

    public function enable(string $watcherId): void
    {
        try {
            $this->driver->enable($watcherId);
        } catch (InvalidWatcherError $e) {
            throw new InvalidWatcherError(
                $watcherId,
                $e->getMessage() . "\r\n\r\n" .
                "** Creation Trace **\r\n" . $this->getCreationTrace($watcherId) . "\r\n\r\n" .
                "** Cancel Trace **\r\n" . $this->getCancelTrace($watcherId)
            );
        }
    }

    public function cancel(string $watcherId): void
    {
        $this->driver->cancel($watcherId);
        $this->creationTraces[$watcherId] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
    }

    public function disable(string $watcherId): void
    {
        $this->driver->disable($watcherId);
    }

    public function reference(string $watcherId): void
    {
        try {
            $this->driver->reference($watcherId);
        } catch (InvalidWatcherError $e) {
            throw new InvalidWatcherError(
                $watcherId,
                $e->getMessage() . "\r\n\r\n" .
                "** Creation Trace **\r\n" . $this->getCreationTrace($watcherId) . "\r\n\r\n" .
                "** Cancel Trace **\r\n" . $this->getCancelTrace($watcherId)
            );
        }
    }

    public function unreference(string $watcherId): void
    {
        $this->driver->unreference($watcherId);
    }

    public function setErrorHandler(callable $callback = null): ?callable
    {
        return $this->driver->setErrorHandler($callback);
    }


    /** @inheritdoc */
    protected function activate(array $watchers): void
    {
        // nothing to do in a decorator
    }

    /** @inheritdoc */
    protected function dispatch(bool $blocking): void
    {
        // nothing to do in a decorator
    }

    /** @inheritdoc */
    protected function deactivate(Watcher $watcher): void
    {
        // nothing to do in a decorator
    }

    /** @inheritdoc */
    public function getHandle()
    {
        $this->driver->getHandle();
    }

    public function getDump(): string
    {
        $dump = "";

        foreach ($this->creationTraces as $watcherId => $creationTrace) {
            if (isset($this->cancelTraces[$watcherId])) {
                continue;
            }

            $dump .= "** Creation Trace **\r\n" . $this->getCreationTrace($watcherId) . "\r\n\r\n";
        }

        return $dump;
    }

    private function getCreationTrace(string $watcher): string
    {
        return $this->creationTraces[$watcher] ?? "Watcher has never been created.";
    }

    private function getCancelTrace(string $watcher): string
    {
        return $this->cancelTraces[$watcher] ?? "Watcher has not been cancelled.";
    }
}
