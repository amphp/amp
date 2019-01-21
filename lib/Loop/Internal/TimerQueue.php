<?php

namespace Amp\Loop\Internal;

use Amp\Loop\Watcher;

/**
 * Uses a binary tree stored in an array to implement a heap.
 */
class TimerQueue
{
    /** @var TimerQueueEntry[] */
    private $data = [];

    /**
     * Inserts the watcher into the queue. Time complexity: O(log(n)).
     *
     * @param Watcher $watcher
     * @param int $expiration
     */
    public function insert(Watcher $watcher, int $expiration)
    {
        $entry = new TimerQueueEntry;
        $entry->watcher = $watcher;
        $entry->expiration = $expiration;

        $node = \count($this->data);
        $this->data[$node] = $entry;

        while ($node !== 0 && $entry->expiration < $this->data[$parent = ($node - 1) >> 1]->expiration) {
            $this->data[$node] = $this->data[$parent];
            $this->data[$parent] = $entry;

            $node = $parent;
        }
    }

    /**
     * Removes the given watcher from the queue. Time complexity: O(n).
     *
     * @param Watcher $watcher
     */
    public function remove(Watcher $watcher)
    {
        foreach ($this->data as $node => $entry) {
            if ($entry->watcher === $watcher) {
                $this->removeAndRebuild($node);
                return;
            }
        }
    }

    /**
     * Deletes and returns the Watcher on top of the heap. Time complexity: O(log(n)).
     *
     * @return  [Watcher, int] Tuple of the watcher and the expiration time.
     */
    public function extract(): array
    {
        if ($this->isEmpty()) {
            throw new \Error('No data left in the heap.');
        }

        $data = $this->removeAndRebuild(0);

        return [$data->watcher, $data->expiration];
    }

    /**
     * @param int $node Remove the given node and then rebuild the data array from that node downward.
     *
     * @return TimerQueueEntry Removed entry.
     */
    private function removeAndRebuild(int $node): TimerQueueEntry
    {
        $length = \count($this->data) - 1;
        $data = $this->data[$node];
        $this->data[$node] = $this->data[$length];
        unset($this->data[$length]);

        while (($child = ($node << 1) + 1) < $length) {
            if ($this->data[$child]->expiration < $this->data[$node]->expiration
                && ($child + 1 >= $length || $this->data[$child]->expiration < $this->data[$child + 1]->expiration)
            ) {
                // Left child is less than parent and right child.
                $swap = $child;
            } elseif ($child + 1 < $length && $this->data[$child + 1]->expiration < $this->data[$node]->expiration) {
                // Right child is less than parent and left child.
                $swap = $child + 1;
            } else { // Left and right child are greater than parent.
                break;
            }

            $temp = $this->data[$node];
            $this->data[$node] = $this->data[$swap];
            $this->data[$swap] = $temp;
            $node = $swap;
        }

        return $data;
    }

    /**
     * Returns the value at the top of the heap (without removing it). Time complexity: O(1).
     *
     * @return  [Watcher, int] Tuple of the watcher and the expiration time.
     */
    public function peek(): array
    {
        if ($this->isEmpty()) {
            throw new \Error('No data in the heap.');
        }

        return [$this->data[0]->watcher, $this->data[0]->expiration];
    }

    /**
     * Determines if the heap is empty.
     * @return  bool
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
