<?php

namespace Alert;

class Aggregate {

    /**
     * Create a new Future that will resolve when all Futures in the array resolve
     *
     * @param array $futures
     * @return Future
     */
    public static function all(array $futures) {
        return (new PromiseGroup($futures))->getFuture();
    }

}
