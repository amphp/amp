<?php

namespace Amp;

interface Streamable {
    /**
     * @return \Generator
     */
    public function stream();
    
    /**
     * @return \Generator
     */
    public function buffer();
}
