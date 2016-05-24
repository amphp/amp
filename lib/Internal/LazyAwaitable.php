<?php

namespace Amp\Internal;

use Amp\Failure;
use Amp\Success;
use Interop\Async\Awaitable;

class LazyAwaitable implements \Interop\Async\Awaitable {
    /**
     * @var callable|null
     */
    private $provider;

    /**
     * @var \Interop\Async\Awaitable
     */
    private $awaitable;

    /**
     * @param callable $provider
     */
    public function __construct(callable $provider) {
        $this->provider = $provider;
    }

    /**
     * @return \Interop\Async\Awaitable
     */
    protected function getAwaitable() {
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

        return $this->awaitable;
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        $this->getAwaitable()->when($onResolved);
    }
}