<?php

namespace Amp;

use Revolt\EventLoop;

final class CombinedCancellationToken implements CancellationToken
{
    /** @var array<int, array{CancellationToken, string}> */
    private array $tokens = [];

    private string $nextId = "a";

    /** @var callable(CancelledException)[] */
    private array $callbacks = [];

    private ?CancelledException $exception = null;

    public function __construct(CancellationToken ...$tokens)
    {
        foreach ($tokens as $token) {
            $id = $token->subscribe(function (CancelledException $exception): void {
                $this->exception = $exception;

                $callbacks = $this->callbacks;
                $this->callbacks = [];

                foreach ($callbacks as $callback) {
                    EventLoop::queue($callback, $exception);
                }
            });

            $this->tokens[] = [$token, $id];
        }
    }

    public function __destruct()
    {
        foreach ($this->tokens as [$token, $id]) {
            /** @var CancellationToken $token */
            $token->unsubscribe($id);
        }
    }

    public function subscribe(\Closure $callback): string
    {
        $id = $this->nextId++;

        if ($this->exception) {
            EventLoop::queue($callback, $this->exception);
        } else {
            $this->callbacks[$id] = $callback;
        }

        return $id;
    }

    /** @inheritdoc */
    public function unsubscribe(string $id): void
    {
        unset($this->callbacks[$id]);
    }

    /** @inheritdoc */
    public function isRequested(): bool
    {
        foreach ($this->tokens as [$token]) {
            if ($token->isRequested()) {
                return true;
            }
        }

        return false;
    }

    /** @inheritdoc */
    public function throwIfRequested(): void
    {
        foreach ($this->tokens as [$token]) {
            $token->throwIfRequested();
        }
    }
}
