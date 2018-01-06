<?php

namespace Amp;

/**
 * A Promise/Coroutine-aware implementation of the Mediator pattern.
 *
 * @link https://en.wikipedia.org/wiki/Mediator_pattern
 */
class Mediator {
    private $eventSubscriberMap = [];

    /**
     * Invoke the callback when the specified event is published to the Mediator.
     *
     * If a subscriber callback returns FALSE (===) or a generator\promise that
     * resolves to FALSE that callback will be unsubscribed from the event.
     *
     * @param string $eventName The name of the event being subscribed to
     * @param callable $callback The callback to invoke when the event is published
     * @return void
     */
    public function subscribe(string $eventName, callable $callback) {
        $this->eventSubscriberMap[$eventName][] = $callback;
    }

    /**
     * Publish an event with associated data payload for consumption by subscribers.
     *
     * If a subscriber callback returns FALSE (===) or a generator\promise that
     * resolves to FALSE that callback will be unsubscribed from the event.
     *
     * @param string $eventName The name of the event to publish
     * @param mixed $data Data associated with the event
     * @return \Amp\Promise<int> Resolves to the number of subscribers invoked
     */
    public function publish(string $eventName, ...$data): Promise {
        if (empty($this->eventSubscriberMap[$eventName])) {
            return new Success(0);
        }
        return new Coroutine((function () use ($eventName, $data) {
            foreach ($this->eventSubscriberMap[$eventName] as $id => $callback) {
                $promises[$id] = call($callback, ...$data);
            }
            list($errors, $results) = yield Promise\any($promises);
            // remove subscribers that returned FALSE (===)
            foreach ($results as $id => $result) {
                if ($result === false) {
                    unset($this->eventSubscriberMap[$eventName][$id]);
                }
            }
            // don't leak memory even if it's just an empty array
            if (empty($this->eventSubscriberMap[$eventName])) {
                unset($this->eventSubscriberMap[$eventName]);
            }
            if ($errors) {
                throw new MultiReasonException(
                    $errors,
                    "Event subscriber(s) threw uncaught exceptions while reacting to {$eventName}"
                );
            }
            return \count($results);
        })());
    }

    public function __debugInfo() {
        $info = [];
        foreach ($this->eventSubscriberMap as $eventName => $callbacks) {
            $info[$eventName] = \count($callbacks);
        }
        return $info;
    }
}
