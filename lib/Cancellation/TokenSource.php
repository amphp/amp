<?php

namespace Amp\Cancellation;

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
 * @see Token
 * @see CancelledException
 */
final class TokenSource
{
    private $token;
    private $onCancel;

    public function __construct()
    {
        $this->token = new class($this->onCancel) implements Token
        {
            /** @var string */
            private $nextId = "a";

            /** @var callable[] */
            private $callbacks = [];

            /** @var \Throwable|null */
            private $exception;

            public function __construct(&$onCancel)
            {
                $onCancel = function (\Throwable $exception) {
                    $this->exception = $exception;

                    $callbacks = $this->callbacks;
                    $this->callbacks = [];

                    foreach ($callbacks as $callback) {
                        $callback($this->exception);
                    }
                };
            }

            public function subscribe(callable $callback): string
            {
                $id = $this->nextId++;

                if ($this->exception) {
                    $callback($this->exception);
                } else {
                    $this->callbacks[$id] = $callback;
                }

                return $id;
            }

            public function unsubscribe(string $id): void
            {
                unset($this->callbacks[$id]);
            }

            public function isRequested(): bool
            {
                return $this->exception !== null;
            }

            public function throwIfRequested(): void
            {
                if ($this->exception !== null) {
                    throw $this->exception;
                }
            }
        };
    }

    public function getToken(): Token
    {
        return $this->token;
    }

    /**
     * @param \Throwable|null $previous Exception to be used as the previous exception to CancelledException.
     */
    public function cancel(\Throwable $previous = null): void
    {
        if ($this->onCancel === null) {
            return;
        }

        $onCancel = $this->onCancel;
        $this->onCancel = null;
        $onCancel(new CancelledException("The operation was cancelled", $previous));
    }
}
