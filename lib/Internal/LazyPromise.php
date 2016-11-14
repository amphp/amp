<?php declare(strict_types = 1);

namespace Amp\Internal;

use Amp\{ Failure, Success };
use Interop\Async\Promise;

/**
 * Promise returned from Amp\lazy(). Use Amp\lazy() instead of instigating this object directly.
 *
 * @internal
 */
class LazyPromise implements Promise {
    /** @var callable|null */
    private $provider;

    /** @var \Interop\Async\Promise|null */
    private $promise;

    /**
     * @param callable $provider
     */
    public function __construct(callable $provider) {
        $this->provider = $provider;
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        if (null === $this->promise) {
            $provider = $this->provider;
            $this->provider = null;

            try {
                $this->promise = $provider();

                if ($this->promise instanceof Promise) {
                    $this->promise = new Success($this->promise);
                }
            } catch (\Throwable $exception) {
                $this->promise = new Failure($exception);
            }
        }

        $this->promise->when($onResolved);
    }
}