<?php

namespace Amp\Promise;

/**
 * Global error handler for promises.
 *
 * Callbacks passed to `Promise::when()` should never throw, but they might. Such errors have to be passed to this
 * global error handler to make them easily loggable. These can't be handled gracefully in any way, so we just enable
 * logging with this handler and ignore them otherwise.
 *
 * If no handler is set or that handler rethrows, it will fail hard by triggering an E_USER_ERROR leading to script
 * abortion.
 */
final class ErrorHandler {
    /** @var callable|null */
    private static $callback = null;

    private function __construct() {
        // disable construction, only static helper
    }

    /**
     * Set a new handler that will be notified on uncaught errors during promise resolution callback invocations.
     *
     * This callback can attempt to log the error or exit the execution of the script if it sees need. It receives the
     * exception as first and only parameter.
     *
     * As it's already a last chance handler, the script will be aborted using E_USER_ERROR if the handler throws. Thus
     * it's suggested to always wrap the body of your callback in a generic `try` / `catch` block, if you want to avoid
     * that.
     *
     * @param callable|null $onError Callback to invoke on errors or `null` to reset.
     *
     * @return callable|null Previous callback.
     */
    public static function set(callable $onError = null) {
        $previous = self::$callback;
        self::$callback = $onError;
        return $previous;
    }

    /**
     * Notifies the registered handler, that an exception occurred.
     *
     * This method MUST be called by every promise implementation if a callback passed to `Promise::when()` throws upon
     * invocation. It MUST NOT be called otherwise.
     *
     * @param \Throwable $error Exception that occurred.
     */
    public static function notify(\Throwable $error) {
        if (self::$callback === null) {
            self::triggerErrorHandler(
                "An exception has been thrown from an AsyncInterop\\Promise::when() handler, but no handler has been"
                . " registered via AsyncInterop\\Promise\\ErrorHandler::set(). A handler has to be registered to"
                . " prevent exceptions from going unnoticed. Do NOT install an empty handler that just does nothing."
                . " If the handler is called, there is ALWAYS something wrong.",
                $error
            );

            return;
        }

        try {
            \call_user_func(self::$callback, $error);
        } catch (\Exception $e) {
            self::triggerErrorHandler(
                "An exception has been thrown from the promise error handler registered to"
                . " AsyncInterop\\Promise\\ErrorHandler::set().",
                $e
            );
        } catch (\Throwable $e) {
            self::triggerErrorHandler(
                "An exception has been thrown from the promise error handler registered to"
                . " AsyncInterop\\Promise\\ErrorHandler::set().",
                $e
            );
        }
    }

    private static function triggerErrorHandler($message, $error) {
        // We're already a last chance handler, throwing doesn't make sense, so use E_USER_ERROR.
        // E_USER_ERROR is recoverable by a handler set via set_error_handler, which might throw, too.

        $message .= "\n\n" . (string) $error;

        try {
            \trigger_error($message, E_USER_ERROR);
        } catch (\Exception $e) {
            \set_error_handler(null);
            \trigger_error($message, E_USER_ERROR);
        } catch (\Throwable $e) {
            \set_error_handler(null);
            \trigger_error($message, E_USER_ERROR);
        }
    }
}