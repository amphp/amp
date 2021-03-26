<?php

namespace Amp;

use function Revolt\EventLoop\defer;

final class CombinedCancellationToken implements CancellationToken
{
    /** @var array{0: CancellationToken, 1: string}[] */
    private array $tokens = [];

    private string $nextId = "a";

    /** @var callable[] */
    private array $callbacks = [];

    private CancelledException $exception;

    public function __construct(CancellationToken ...$tokens)
    {
        foreach ($tokens as $token) {
            $id = $token->subscribe(function (CancelledException $exception): void {
                $this->exception = $exception;

                $callbacks = $this->callbacks;
                $this->callbacks = [];

                foreach ($callbacks as $callback) {
                    defer($callback, $this->exception);
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

    /** @inheritdoc */
    public function subscribe(callable $callback): string
    {
        $id = $this->nextId++;

        if (isset($this->exception)) {
            defer($callback, $this->exception);
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
