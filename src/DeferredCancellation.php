<?php declare(strict_types=1);

namespace Amp;

/**
 * A deferred cancellation provides a mechanism to cancel operations dynamically.
 *
 * Cancellation of operation works by creating a deferred cancellation and passing the corresponding cancellation when
 * starting the operation. To cancel the operation, invoke `DeferredCancellation::cancel()`.
 *
 * Any operation can decide what to do on a cancellation request, it has "don't care" semantics. An operation SHOULD be
 * aborted, but MAY continue. Example: A DNS client might continue to receive and cache the response, as the query has
 * been sent anyway. An HTTP client would usually close a connection, but might not do so in case a response is close to
 * be fully received to reuse the connection.
 *
 * **Example**
 *
 * ```php
 * $deferredCancellation = new DeferredCancellation;
 * $cancellation = $deferredCancellation->getCancellation();
 *
 * $response = $httpClient->request("https://example.com/pipeline", $cancellation);
 * $responseBody = $response->getBody();
 *
 * while (null !== $chunk = $response->read()) {
 *     // consume $chunk
 *
 *     if ($noLongerInterested) {
 *         $deferredCancellation->cancel();
 *         break;
 *     }
 * }
 * ```
 *
 * @see Cancellation
 * @see CancelledException
 */
final class DeferredCancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly Internal\Cancellable $source;
    private readonly Cancellation $cancellation;

    public function __construct()
    {
        $this->source = new Internal\Cancellable;
        $this->cancellation = new Internal\WrappedCancellation($this->source);
    }

    public function __destruct()
    {
        $this->source->cancel();
    }

    public function getCancellation(): Cancellation
    {
        return $this->cancellation;
    }

    public function isCancelled(): bool
    {
        return $this->source->isRequested();
    }

    /**
     * @param \Throwable|null $previous Exception to be used as the previous exception to CancelledException.
     */
    public function cancel(?\Throwable $previous = null): void
    {
        $this->source->cancel($previous);
    }
}
