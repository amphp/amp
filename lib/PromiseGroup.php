<?php

namespace Alert;

class PromiseGroup extends Promise {
    private $futureGroup = [];
    private $resolvedValues = [];
    private $isComplete = FALSE;

    public function __construct(array $futureGroup) {
        parent::__construct();
        
        if (!$futureGroup = array_filter($futureGroup)) {
            $this->succeed();
            return;
        }

        $this->futureGroup = $futureGroup;

        foreach ($futureGroup as $key => $future) {
            if (!$future instanceof Future) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Future array required at Argument 1: %s provided at index %s',
                        gettype($future),
                        $key
                    )
                );
            }

            $isComplete = $future->isComplete();

            if ($isComplete && $this->resolveIndividualFuture($future, $key)) {
                return;
            } elseif (!$isComplete) {
                $future->onComplete(function($future) use ($key) {
                    $this->resolveIndividualFuture($future, $key);
                });
            }
        }
    }

    private function resolveIndividualFuture($future, $key) {
        unset($this->futureGroup[$key]);

        if ($this->isComplete) {
            return TRUE;
        } elseif ($future->succeeded()) {
            $this->resolvedValues[$key] = $future->getValue();
            return ($this->isComplete = empty($this->futureGroup))
                ? parent::succeed($this->resolvedValues)
                : FALSE;
        } else {
            parent::fail($future->getError());
            return ($this->isComplete = TRUE);
        }
    }
}
