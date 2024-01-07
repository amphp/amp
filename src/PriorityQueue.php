<?php declare(strict_types=1);

namespace Amp;

/**
 * Uses a binary tree stored in an array to implement a heap.
 *
 * @template T of int|string
 */
final class PriorityQueue
{
    /** @var array<int, object{key: T, priority: int}> */
    private array $data = [];

    /** @var array<T, int> */
    private array $pointers = [];

    /**
     * Inserts the key into the queue with the given priority or updates the priority if the key
     * already exists in the queue.
     *
     * Time complexity: O(log(n)).
     *
     * @param T $key
     */
    public function insert(int|string $key, int $priority): void
    {
        if (isset($this->pointers[$key])) {
            $node = $this->pointers[$key];
            $entry = $this->data[$node];

            $previous = $entry->priority;
            $entry->priority = $priority;

            // Nothing to be done if priorities are equal.
            if ($previous < $priority) {
                $this->heapifyDown($node);
            } elseif ($previous > $priority) {
                $this->heapifyUp($node);
            }

            return;
        }

        $entry = new class($key, $priority) {
            public function __construct(
                public readonly int|string $key,
                public int $priority,
            ) {
            }
        };

        $node = \count($this->data);
        $this->data[$node] = $entry;
        $this->pointers[$key] = $node;

        $this->heapifyUp($node);
    }

    /**
     * Removes the given key from the queue.
     *
     * Time complexity: O(log(n)).
     *
     * @param T $key
     */
    public function remove(int|string $key): void
    {
        if (!isset($this->pointers[$key])) {
            return;
        }

        $this->removeAndRebuild($this->pointers[$key]);
    }

    /**
     * Deletes and returns the data at the top of the queue if the priority is less than the priority given.
     *
     * Time complexity: O(log(n)).
     *
     * @param int $priority Extract data with a priority less than the given priority.
     *
     * @return T|null
     */
    public function extract(int $priority = \PHP_INT_MAX): int|string|null
    {
        $data = $this->data[0] ?? null;
        if ($data === null || $data->priority > $priority) {
            return null;
        }

        $this->removeAndRebuild(0);

        return $data->key;
    }

    /**
     * Returns the data at top of the heap or null if empty. Time complexity: O(1).
     *
     * @return T|null
     */
    public function peekData(): int|string|null
    {
        return ($this->data[0] ?? null)?->key;
    }

    /**
     * Returns the priority at top of the heap or null if empty. Time complexity: O(1).
     */
    public function peekPriority(): ?int
    {
        return ($this->data[0] ?? null)?->priority;
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * @param int $node Rebuild the data array from the given node upward.
     */
    private function heapifyUp(int $node): void
    {
        $entry = $this->data[$node];
        while ($node !== 0 && $entry->priority < $this->data[$parent = ($node - 1) >> 1]->priority) {
            $this->swap($node, $parent);
            $node = $parent;
        }
    }

    /**
     * @param int $node Rebuild the data array from the given node downward.
     */
    private function heapifyDown(int $node): void
    {
        $length = \count($this->data);
        while (($child = ($node << 1) + 1) < $length) {
            if ($this->data[$child]->priority < $this->data[$node]->priority
                && ($child + 1 >= $length || $this->data[$child]->priority < $this->data[$child + 1]->priority)
            ) {
                // Left child is less than parent and right child.
                $swap = $child;
            } elseif ($child + 1 < $length && $this->data[$child + 1]->priority < $this->data[$node]->priority) {
                // Right child is less than parent and left child.
                $swap = $child + 1;
            } else { // Left and right child are greater than parent.
                break;
            }

            $this->swap($node, $swap);
            $node = $swap;
        }
    }

    private function swap(int $left, int $right): void
    {
        $temp = $this->data[$left];

        $this->data[$left] = $this->data[$right];
        $this->pointers[$this->data[$right]->key] = $left;

        $this->data[$right] = $temp;
        $this->pointers[$temp->key] = $right;
    }

    /**
     * @param int $node Remove the given node and then rebuild the data array.
     */
    private function removeAndRebuild(int $node): void
    {
        $length = \count($this->data) - 1;
        $id = $this->data[$node]->key;
        $left = $this->data[$node] = $this->data[$length];
        $this->pointers[$left->key] = $node;
        unset($this->data[$length], $this->pointers[$id]);

        if ($node < $length) { // don't need to do anything if we removed the last element
            $parent = ($node - 1) >> 1;
            if ($parent >= 0 && $this->data[$node]->priority < $this->data[$parent]->priority) {
                $this->heapifyUp($node);
            } else {
                $this->heapifyDown($node);
            }
        }
    }
}
