<?php

namespace Amp;

interface Observable
{
    /**
     * @return \Amp\ObservableIterator
     */
    public function getIterator();

    /**
     * Disposes of the observable, halting emission of values and failing the observable with an instance of
     * \Amp\DisposedException.
     */
    public function dispose();
}
