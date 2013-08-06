<?php

namespace SymphonyCms\Exceptions;

use \ErrorException;
use \SymphonyCms\Symphony\Log;

/**
 * `GenericErrorHandler` will catch any warnings or notices thrown by PHP and
 * raise the errors to Exceptions so they can be dealt with by the
 * `GenericExceptionHandler`. The type of errors that are raised to Exceptions
 * depends on the `error_reporting` level. All errors raised, except
 * `E_NOTICE` and `E_STRICT` are written to the Symphony log.
 */
class GenericErrorHandler
{
    /**
     * Whether the error handler is enabled or not, defaults to true.
     * Setting to false will prevent any Symphony error handling from occurring
     * @var boolean
     */
    public static $enabled = true;

    /**
     * An instance of the Log class, used to write errors to the log
     * @var Log
     */
    private static $Log = null;

    /**
     * Whether to log errors or not.
     * This one is to be used temporarily, e.g., when PHP function is
     * supposed to error out and throw warning and log should be kept clean.
     *
     * @since Symphony 2.2.2
     * @var boolean
     * @example
     *  GenericErrorHandler::$logDisabled = true;
     *  DoSomethingThatEndsWithWarningsYouDoNotWantInLogs();
     *  GenericErrorHandler::$logDisabled = false;
     */
    public static $logDisabled = false;

    /**
     * An associative array with the PHP error constant as a key, and
     * a string describing that constant as the value
     * @var array
     */
    public static $errorTypeStrings = array(
        E_NOTICE                => 'Notice',
        E_WARNING               => 'Warning',
        E_ERROR                 => 'Error',
        E_PARSE                 => 'Parsing Error',

        E_CORE_ERROR            => 'Core Error',
        E_CORE_WARNING          => 'Core Warning',
        E_COMPILE_ERROR         => 'Compile Error',
        E_COMPILE_WARNING       => 'Compile Warning',

        E_USER_NOTICE           => 'User Notice',
        E_USER_WARNING          => 'User Warning',
        E_USER_ERROR            => 'User Error',

        E_STRICT                => 'Strict Notice',
        E_RECOVERABLE_ERROR     => 'Recoverable Error',
        E_DEPRECATED            => 'Deprecated Warning'
    );

    /**
     * Initialise will set the error handler to be the `__CLASS__::handler`
     * function.
     *
     * @param Log|null $Log (optional)
     *  An instance of a Symphony Log object to write errors to
     */
    public static function initialise(Log $Log = null)
    {
        if (!is_null($Log)) {
            self::$Log = $Log;
        }

        set_error_handler(array(__CLASS__, 'handler'), error_reporting());
    }

    /**
     * Determines if the error handler is enabled by checking that error_reporting
     * is set in the php config and that $enabled is true
     *
     * @return boolean
     */
    public static function isEnabled()
    {
        return (bool)(error_reporting() && self::$enabled);
    }

    /**
     * The handler function will write the error to the `$Log` if it is not `E_NOTICE`
     * or `E_STRICT` before raising the error as an Exception. This allows all `E_WARNING`
     * to actually be captured by an Exception handler.
     *
     * @param integer $code
     *  The error code, one of the PHP error constants
     * @param string $message
     *  The message of the error, this will be written to the log and
     *  displayed as the exception message
     * @param string $file
     *  The file that holds the logic that caused the error. Defaults to null
     * @param integer $line
     *  The line where the error occurred.
     * @return string
     *  Usually a string of HTML that will displayed to a user
     */
    public static function handler($code, $message, $file = null, $line = null)
    {
        // Only log if the error won't be raised to an exception and the error is not `E_STRICT`
        if (!self::$logDisabled && !in_array($code, array(E_STRICT)) && self::$Log instanceof Log) {
            self::$Log->pushToLog(
                sprintf(
                    '%s %s: %s%s%s',
                    __CLASS__,
                    $code,
                    $message,
                    ($line ? " on line $line" : null),
                    ($file ? " of file $file" : null)
                ),
                $code,
                true
            );
        }

        if (self::isEnabled()) {
            throw new ErrorException($message, 0, $code, $file, $line);
        }
    }
}
