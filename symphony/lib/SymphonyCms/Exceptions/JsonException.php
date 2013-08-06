<?php

namespace Symphony\Exceptions;

use \Exception;

/**
 * The `JSONException` class extends the base `Exception` class. It's only
 * difference is that it will translate the `$code` to a human readable
 * error.
 *
 * @since Symphony 2.3
 */
class JsonException extends Exception
{
    /**
     * Constructor takes a `$message`, `$code` and the original Exception, `$ex`.
     * Upon translating the `$code` into a more human readable message, it will
     * initialise the base `Exception` class. If the `$code` is unfamiliar, the original
     * `$message` will be passed.
     *
     * @param string $message
     * @param int $code
     * @param Exception $ex
     */
    public function __construct($message, $code = null, Exception $ex = null)
    {
        switch ($code) {
            case JSON_ERROR_NONE:
                $message = tr('No errors.');
            break;
            case JSON_ERROR_DEPTH:
                $message = tr('Maximum stack depth exceeded.');
            break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = tr('Underflow or the modes mismatch.');
            break;
            case JSON_ERROR_CTRL_CHAR:
                $message = tr('Unexpected control character found.');
            break;
            case JSON_ERROR_SYNTAX:
                $message = tr('Syntax error, malformed JSON.');
            break;
            case JSON_ERROR_UTF8:
                $message = tr('Malformed UTF-8 characters, possibly incorrectly encoded.');
            break;
            default:
                $message = tr('Unknown JSON error');
            break;
        }

        parent::__construct($message, $code, $ex);
    }
}
