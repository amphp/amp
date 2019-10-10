<?php

namespace Amp;

use function Amp\Internal\getCurrentTime;

final class Timing
{
    private $timings = [];

    public function start(string $key)
    {
        if (isset($this->timings[$key])) {
            $time = getCurrentTime() - $this->timings[$key][0];
            throw new \Error("Timer '$key' has already been started $time milliseconds ago");
        }

        $this->timings[$key] = [getCurrentTime()];
    }

    public function end(string $key)
    {
        $now = getCurrentTime();

        if (!isset($this->timings[$key])) {
            throw new \Error("Timer '$key' has not been started, yet");
        }

        if (\count($this->timings[$key]) !== 1) {
            $time = $now - $this->timings[$key][1];
            throw new \Error("Timer '$key' has already been stopped $time milliseconds ago");
        }

        $this->timings[$key][1] = $now;
    }

    public function getDuration(string $key): int
    {
        if (!isset($this->timings[$key])) {
            throw new \Error("Timer '$key' has not been started, yet");
        }

        if (\count($this->timings[$key]) === 1) {
            return getCurrentTime() - $this->timings[$key][0];
        }

        return $this->timings[$key][1] - $this->timings[$key][0];
    }

    /** @return string[] */
    public function getKeys(): array
    {
        return \array_map('\strval', \array_keys($this->timings));
    }

    public function __debugInfo(): array
    {
        $entries = [];

        foreach ($this->timings as $key => $timings) {
            if (\count($timings) === 1) {
                $entries[$key] = $this->getDuration($key) . ' ms (ongoing)';
            } else {
                $entries[$key] = $this->getDuration($key) . ' ms';
            }
        }

        return $entries;
    }
}
