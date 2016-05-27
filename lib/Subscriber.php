<?php

namespace Amp;

use Interop\Async\Awaitable;

final class Subscriber implements Disposable {
    /**
     * @var \Amp\Coroutine
     */
    private $coroutine;

    /**
     * @var bool
     */
    private $subscribed = true;

    /**
     * @param callable $onNext
     * @param \Amp\Internal\Subscription $subscription
     */
    public function __construct(callable $onNext, Internal\Subscription $subscription) {
        $this->coroutine = new Coroutine($this->run($onNext, $subscription));
    }

    /**
     * @coroutine
     *
     * @param callable $onNext
     * @param \Amp\Internal\Subscription $subscription
     *
     * @return \Generator
     *
     * @throws \Throwable|\Exception
     */
    private function run(callable $onNext, Internal\Subscription $subscription) {
        try {
            while ($this->subscribed) {
                /** @var \Amp\Internal\Emitted $emitted */
                $emitted = (yield $subscription->pull());

                try {
                    $value = $emitted->getValue();

                    if ($value instanceof Awaitable) {
                        $value = (yield $value);
                    }

                    if ($emitted->isComplete()) {
                        yield Coroutine::result($value);
                        return;
                    }

                    $result = $onNext($value);

                    if ($result instanceof Awaitable) {
                        yield $result;
                    }
                } finally {
                    $emitted->ready();
                }
            }
        } finally {
            $subscription->unsubscribe();
        }

        throw new DisposedException("The subscriber was disposed");
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        $this->coroutine->when($onResolved);
    }

    /**
     * {@inheritdoc}
     */
    public function dispose() {
        $this->subscribed = false;
    }
}
