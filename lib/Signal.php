<?php

namespace Amp;

final class Signal implements Promise
{
    private Internal\Placeholder $placeholder;

    /** @var string[] */
    private array $watchers = [];

    public function __construct(int $signal, int ...$signals)
    {
        $this->placeholder = $placeholder = new Internal\Placeholder;

        \array_unshift($signals, $signal);

        $watchers = &$this->watchers;
        foreach ($signals as $signal) {
            $this->watchers[] = Loop::onSignal($signal, static function (string $id, int $signo) use (
                &$watchers,
                $placeholder
            ): void {
                foreach ($watchers as $watcher) {
                    Loop::cancel($watcher);
                }
                $watchers = [];

                $placeholder->resolve($signo);
            });
        }
    }

    public function __destruct()
    {
        foreach ($this->watchers as $watcher) {
            Loop::cancel($watcher);
        }
    }

    /**
     * References the internal watcher in the event loop, keeping the loop running while this promise is pending.
     *
     * @return self
     */
    public function reference(): self
    {
        foreach ($this->watchers as $watcher) {
            Loop::reference($watcher);
        }

        return $this;
    }

    /**
     * Unreferences the internal watcher in the event loop, allowing the loop to stop while this promise is pending if
     * no other events are pending in the loop.
     *
     * @return self
     */
    public function unreference(): self
    {
        foreach ($this->watchers as $watcher) {
            Loop::unreference($watcher);
        }

        return $this;
    }

    /** @inheritDoc */
    public function onResolve(callable $onResolved): void
    {
        $this->placeholder->onResolve($onResolved);
    }
}
