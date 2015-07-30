<?php

namespace Amp;
/**
 * This class exists to provide faux enum values in internal Reactor code.
 * Applications should not rely upon these values as part of the public API.
 */
final class Watcher {
    const IMMEDIATE    = 0b00000001;
    const TIMER        = 0b00000010;
    const TIMER_ONCE   = 0b00000110;
    const TIMER_REPEAT = 0b00001010;
    const IO           = 0b00010000;
    const IO_READER    = 0b00110000;
    const IO_WRITER    = 0b01010000;
    const SIGNAL       = 0b10000000;
}
