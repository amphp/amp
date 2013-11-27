<?php

namespace Alert;

interface Forkable {
    function beforeFork();
    function afterFork();
}
