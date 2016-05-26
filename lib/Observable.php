<?php

namespace Amp;

interface Observable {
    /**
     * Returns an observer of the observable.
     *
     * @return \Amp\Observer
     */
    public function getObserver();
}
