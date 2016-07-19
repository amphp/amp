<?php

namespace Amp\Internal;

use Amp\Failure;
use Amp\Success;
use Interop\Async\Awaitable;

/**
 * Awaitable returned from Amp\lazy(). Use Amp\lazy() instead of instigating this object directly.
 *
 * @internal
 */
class LazyAwaitable implements Awaitable {
    /**
     * @var callable|null
     */
    private $provider;

    /**
     * @var \Interop\Async\Awaitable|null
     */
    private $awaitable;

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
        if (null === $this->awaitable) {
            $provider = $this->provider;
            $this->provider = null;

            try {
                $this->awaitable = $provider();

                if ($this->awaitable instanceof Awaitable) {
                    $this->awaitable = new Success($this->awaitable);
                }
            } catch (\Throwable $exception) {
                $this->awaitable = new Failure($exception);
            } catch (\Exception $exception) {
                $this->awaitable = new Failure($exception);
            }
        }

        $this->awaitable->when($onResolved);
    }
}