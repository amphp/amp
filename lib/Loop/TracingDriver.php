<?php

namespace Amp\Loop;

use function Amp\Internal\formatStacktrace;

final class TracingDriver extends Driver
{
    private $driver;
    private $enabledWatchers = [];
    private $unreferencedWatchers = [];
    private $creationTraces = [];
    private $cancelTraces = [];

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    public function run()
    {
        $this->driver->run();
    }

    public function stop()
    {
        $this->driver->stop();
    }

    public function defer(callable $callback, $data = null): string
    {
        $id = $this->driver->defer(function (...$args) use ($callback) {
            $this->cancel($args[0]);
            return $callback(...$args);
        }, $data);

        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        $this->enabledWatchers[$id] = true;

        return $id;
    }

    public function delay(int $delay, callable $callback, $data = null): string
    {
        $id = $this->driver->delay($delay, function (...$args) use ($callback) {
            $this->cancel($args[0]);
            return $callback(...$args);
        }, $data);

        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        $this->enabledWatchers[$id] = true;

        return $id;
    }

    public function repeat(int $interval, callable $callback, $data = null): string
    {
        $id = $this->driver->repeat($interval, $callback, $data);

        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        $this->enabledWatchers[$id] = true;

        return $id;
    }

    public function onReadable($stream, callable $callback, $data = null): string
    {
        $id = $this->driver->onReadable($stream, $callback, $data);

        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        $this->enabledWatchers[$id] = true;

        return $id;
    }

    public function onWritable($stream, callable $callback, $data = null): string
    {
        $id = $this->driver->onWritable($stream, $callback, $data);

        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        $this->enabledWatchers[$id] = true;

        return $id;
    }

    public function onSignal(int $signo, callable $callback, $data = null): string
    {
        $id = $this->driver->onSignal($signo, $callback, $data);

        $this->creationTraces[$id] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));
        $this->enabledWatchers[$id] = true;

        return $id;
    }

    public function enable(string $watcherId)
    {
        try {
            $this->driver->enable($watcherId);
            $this->enabledWatchers[$watcherId] = true;
        } catch (InvalidWatcherError $e) {
            throw new InvalidWatcherError(
                $watcherId,
                $e->getMessage() . "\r\n\r\nCreation Trace:\r\n" . $this->getCreationTrace($watcherId) . "\r\n\r\nCancel Trace:\r\n" . $this->getCancelTrace($watcherId)
            );
        }
    }

    public function cancel(string $watcherId)
    {
        $this->driver->cancel($watcherId);
        $this->creationTraces[$watcherId] = formatStacktrace(\debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS));

        unset($this->enabledWatchers[$watcherId], $this->unreferencedWatchers[$watcherId]);
    }

    public function disable(string $watcherId)
    {
        $this->driver->disable($watcherId);
        unset($this->enabledWatchers[$watcherId]);
    }

    public function reference(string $watcherId)
    {
        try {
            $this->driver->reference($watcherId);
            unset($this->unreferencedWatchers[$watcherId]);
        } catch (InvalidWatcherError $e) {
            throw new InvalidWatcherError(
                $watcherId,
                $e->getMessage() . "\r\n\r\nCreation Trace:\r\n" . $this->getCreationTrace($watcherId)
            );
        }
    }

    public function unreference(string $watcherId)
    {
        $this->driver->unreference($watcherId);
        $this->unreferencedWatchers[$watcherId] = true;
    }

    public function setErrorHandler(callable $callback = null)
    {
        return $this->driver->setErrorHandler($callback);
    }

    /** @inheritdoc */
    public function getHandle()
    {
        $this->driver->getHandle();
    }

    public function dump(): string
    {
        $dump = "Enabled, referenced watchers keeping the loop running: ";

        foreach ($this->enabledWatchers as $watcher => $_) {
            if (isset($this->unreferencedWatchers[$watcher])) {
                continue;
            }

            $dump .= "Watcher ID: " . $watcher . "\r\n";
            $dump .= $this->getCreationTrace($watcher);
            $dump .= "\r\n\r\n";
        }

        return \rtrim($dump);
    }

    public function getInfo(): array
    {
        return $this->driver->getInfo();
    }

    public function __debugInfo()
    {
        return $this->driver->__debugInfo();
    }

    public function now(): int
    {
        return $this->driver->now();
    }

    protected function error(\Throwable $exception)
    {
        $this->driver->error($exception);
    }

    /** @inheritdoc */
    protected function activate(array $watchers)
    {
        // nothing to do in a decorator
    }

    /** @inheritdoc */
    protected function dispatch(bool $blocking)
    {
        // nothing to do in a decorator
    }

    /** @inheritdoc */
    protected function deactivate(Watcher $watcher)
    {
        // nothing to do in a decorator
    }

    private function getCreationTrace(string $watcher): string
    {
        if (!isset($this->creationTraces[$watcher])) {
            throw new InvalidWatcherError($watcher, "An invalid watcher has been used: " . $watcher);
        }

        return $this->creationTraces[$watcher];
    }

    private function getCancelTrace(string $watcher): string
    {
        if (!isset($this->cancelTraces[$watcher])) {
            throw new InvalidWatcherError($watcher, "An invalid watcher has been used: " . $watcher);
        }

        return $this->cancelTraces[$watcher];
    }
}
