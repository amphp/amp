<?php

namespace Amp;

final class Emitter implements Observable {
    use Internal\Producer {
        emit as public;
        complete as public;
        fail as public;
    }
}
