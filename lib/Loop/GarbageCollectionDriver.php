<?php


namespace Amp\Loop;

class GarbageCollectionDriver extends Driver
{
    protected function activate(array $watchers): void
    {
        throw new \Error("Can't activate watcher during garbage collection.");
    }

    protected function dispatch(bool $blocking): void
    {
        throw new \Error("Can't dispatch during garbage collection.");
    }

    protected function deactivate(Watcher $watcher): void
    {
        // do nothing
    }

    public function getHandle()
    {
        return null;
    }
}
