<?php

namespace Amp;

use React\Promise\PromiseInterface as ReactPromise;
use function Amp\Promise\rethrow;

/**
 * A cancellation token source provides a mechanism to cancel operations.
 *
 * Cancellation of operation works by creating a cancellation token source and passing the corresponding token when
 * starting the operation. To cancel the operation, invoke `CancellationTokenSource::cancel()`.
 *
 * Any operation can decide what to do on a cancellation request, it has "don't care" semantics. An operation SHOULD be
 * aborted, but MAY continue. Example: A DNS client might continue to receive and cache the response, as the query has
 * been sent anyway. An HTTP client would usually close a connection, but might not do so in case a response is close to
 * be fully received to reuse the connection.
 *
 * **Example**
 *
 * ```php
 * $tokenSource = new CancellationTokenSource;
 * $token = $tokenSource->getToken();
 *
 * $response = yield $httpClient->request("https://example.com/stream", $token);
 * $responseBody = $response->getBody();
 *
 * while (($chunk = yield $response->read()) !== null) {
 *     // consume $chunk
 *
 *     if ($noLongerInterested) {
 *         $cancellationTokenSource->cancel();
 *         break;
 *     }
 * }
 * ```
 *
 * @see CancellationToken
 * @see CancelledException
 */
final class CancellationTokenSource {
    private $token;
    private $onCancel;

    public function __construct() {
        $this->token = new class($this->onCancel) implements CancellationToken {
            /** @var string */
            private $nextId = "a";

            /** @var callable[] */
            private $callbacks = [];

            /** @var \Throwable|null */
            private $exception = null;

            public function __construct(&$onCancel) {
                $onCancel = function (\Throwable $exception) {
                    $this->exception = $exception;

                    $callbacks = $this->callbacks;
                    $this->callbacks = [];

                    foreach ($callbacks as $callback) {
                        $this->invokeCallback($callback);
                    }
                };
            }

            private function invokeCallback($callback) {
                // No type declaration to prevent exception outside the try!
                try {
                    $result = $callback($this->exception);

                    if ($result instanceof \Generator) {
                        $result = new Coroutine($result);
                    }

                    if ($result instanceof Promise || $result instanceof ReactPromise) {
                        rethrow($result);
                    }
                } catch (\Throwable $exception) {
                    Loop::defer(static function () use ($exception) {
                        throw $exception;
                    });
                }
            }

            public function subscribe(callable $callback): string {
                $id = $this->nextId++;

                if ($this->exception) {
                    $this->invokeCallback($callback);
                } else {
                    $this->callbacks[$id] = $callback;
                }

                return $id;
            }

            public function unsubscribe(string $id) {
                unset($this->callbacks[$id]);
            }

            public function isRequested(): bool {
                return isset($this->exception);
            }

            public function throwIfRequested() {
                if (isset($this->exception)) {
                    throw $this->exception;
                }
            }
        };
    }

    public function getToken(): CancellationToken {
        return $this->token;
    }

    /**
     * @param \Throwable|null $previous Exception to be used as the previous exception to CancelledException.
     */
    public function cancel(\Throwable $previous = null) {
        if ($this->onCancel === null) {
            return;
        }

        $onCancel = $this->onCancel;
        $this->onCancel = null;
        $onCancel(new CancelledException($previous));
    }
}
