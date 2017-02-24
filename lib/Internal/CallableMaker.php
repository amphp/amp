<?php

namespace Amp\Internal;

// @codeCoverageIgnoreStart
if (\PHP_VERSION_ID < 70100) {
    /**
     * @internal
     */
    trait CallableMaker {
        /** @var \ReflectionClass */
        private static $__reflectionClass;

        /** @var \ReflectionMethod[] */
        private static $__reflectionMethods = [];

        /**
         * Creates a callable from a protected or private instance method that may be invoked by methods requiring a
         * publicly invokable callback.
         *
         * @TODO Once 7.1 is required, this method will no longer be necessary. Use \Closure::fromCallable() instead.
         *
         * @param string $method Instance method name.
         *
         * @return callable
         */
        private function callableFromInstanceMethod(string $method): callable {
            if (!isset(self::$__reflectionMethods[$method])) {
                if (self::$__reflectionClass === null) {
                    self::$__reflectionClass = new \ReflectionClass(self::class);
                }
                self::$__reflectionMethods[$method] = self::$__reflectionClass->getMethod($method);
            }

            return self::$__reflectionMethods[$method]->getClosure($this);
        }

        /**
         * Creates a callable from a protected or private static method that may be invoked by methods requiring a
         * publicly invokable callback.
         *
         * @TODO Once 7.1 is required, this method will no longer be necessary. Use \Closure::fromCallable() instead.
         *
         * @param string $method Static method name.
         *
         * @return callable
         */
        private static function callableFromStaticMethod(string $method): callable {
            if (!isset(self::$__reflectionMethods[$method])) {
                if (self::$__reflectionClass === null) {
                    self::$__reflectionClass = new \ReflectionClass(self::class);
                }
                self::$__reflectionMethods[$method] = self::$__reflectionClass->getMethod($method);
            }

            return self::$__reflectionMethods[$method]->getClosure();
        }
    }
} else {
    trait CallableMaker {
        /**
         * @deprecated Use \Closure::fromCallable() instead of this method in PHP 7.1.
         */
        private function callableFromInstanceMethod(string $method): callable {
            return \Closure::fromCallable([$this, $method]);
        }

        /**
         * @deprecated Use \Closure::fromCallable() instead of this method in PHP 7.1.
         */
        private static function callableFromStaticMethod(string $method): callable {
            return \Closure::fromCallable([self::class, $method]);
        }
    }
} // @codeCoverageIgnoreEnd
