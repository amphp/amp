<?php

namespace Amp;

interface Streamable {
    /**
     * @return \Generator
     */
    public function stream(): \Generator;
    
    /**
     * @return \Generator
     */
    public function buffer(): \Generator;
}
