<?php

namespace Amp;

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
 * $response = yield $httpClient->request("https://example.com/pipeline", $token);
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
final class CancellationTokenSource
{
    private Internal\CancellableToken $source;
    private CancellationToken $token;

    public function __construct()
    {
        $this->source = new Internal\CancellableToken;
        $this->token = new Internal\WrappedCancellationToken($this->source);
    }

    public function getToken(): CancellationToken
    {
        return $this->token;
    }

    /**
     * @param \Throwable|null $previous Exception to be used as the previous exception to CancelledException.
     */
    public function cancel(?\Throwable $previous = null): void
    {
        $this->source->cancel($previous);
    }
}
