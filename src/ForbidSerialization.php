<?php declare(strict_types=1);

namespace Amp;

trait ForbidSerialization
{
    /** @psalm-pure */
    final public function __serialize(): never
    {
        throw new \Error(__CLASS__ . ' does not support serialization');
    }

    /** @psalm-pure */
    final public function __unserialize(array $data): never
    {
        // $data is required by the __unserialize() prototype and is intentionally unused.
        unset($data);

        throw new \Error(__CLASS__ . ' does not support deserialization');
    }
}
