<?php

namespace Amp;

/**
 * A Promise-aware implementation of the Mediator pattern.
 *
 * @link https://en.wikipedia.org/wiki/Mediator_pattern
 */
class Mediator {
    private $nextId = "a";
    private $idEventMap = [];
    private $eventSubscriberMap = [];

    /**
     * Attach an event listener callback to the Mediator.
     *
     * Listener callbacks are invoked with the signature:
     *
     *     callback(Mediator $mediator, string $subscriberId, ...$data)
     *
     * @param string $eventName The name of the event being subscribed to
     * @param callable $callback The callback to invoke when the event is published
     * @return string Returns listener's subscriber ID
     */
    public function subscribe(string $eventName, callable $callback): string {
        $subscriberId = $this->nextId++;
        $this->idEventMap[$subscriberId] = $eventName;
        $this->eventSubscriberMap[$eventName][$subscriberId] = $callback;
        return $subscriberId;
    }

    /**
     * Detach an event listener from the Mediator.
     *
     * @param string $subscriberId The subscriber ID generated registering the listener
     * @return bool Returns TRUE if a listener was removed, FALSE otherwise
     */
    public function unsubscribe(string $subscriberId): bool {
        if (!isset($this->idEventMap[$subscriberId])) {
            return false;
        }
        $eventName = $this->idEventMap[$subscriberId];
        unset(
            $this->idEventMap[$subscriberId],
            $this->eventSubscriberMap[$eventName][$subscriberId]
        );
        // don't leak memory even if it's just an empty array
        if (empty($this->eventSubscriberMap[$eventName])) {
            unset($this->eventSubscriberMap[$eventName]);
        }
        return true;
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
        return empty($this->eventSubscriberMap[$eventName])
            ? new Success(0)
            : new Coroutine($this->doPublish($eventName, $data));
    }

    private function doPublish(string $eventName, array $data): \Generator {
        foreach ($this->eventSubscriberMap[$eventName] as $subscriberId => $callback) {
            $promises[$subscriberId] = call($callback, $this, $subscriberId, ...$data);
        }
        list($errors, $results) = yield Promise\any($promises);
        if ($errors) {
            throw new MultiReasonException(
                $errors,
                "Mediator subscriber(s) threw uncaught exception(s) while reacting to {$eventName}"
            );
        }
        return \count($results);
    }
}
