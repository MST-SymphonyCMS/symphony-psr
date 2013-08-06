<?php

namespace SymphonyCms\Exceptions;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Symphony\Log;
use \SymphonyCms\Exceptions\ErrorException;
use \SymphonyCms\Exceptions\FrontendPageNotFoundException;
use \SymphonyCms\Exceptions\GenericErrorHandler;
use \SymphonyCms\Toolkit\Page;

/**
 * GenericExceptionHandler will handle any uncaught exceptions thrown in
 * Symphony. Additionally, all errors in Symphony that are raised to Exceptions
 * will be handled by this class.
 * It is possible for Exceptions to be caught by their own `ExceptionHandler` which can
 * override the `render` function so that it can be displayed to the user appropriately.
 */
class GenericExceptionHandler
{
    /**
     * Whether the `GenericExceptionHandler` should handle exceptions. Defaults to true
     * @var boolean
     */
    public static $enabled = true;

    /**
     * An instance of the Symphony Log class, used to write errors to the log
     * @var Log
     */
    private static $Log = null;

    /**
     * Initialise will set the error handler to be the `__CLASS__::handler` function.
     *
     * @param Log $log
     *  An instance of a Symphony Log object to write errors to
     */
    public static function initialise(Log $Log = null)
    {
        if (!is_null($Log)) {
            self::$Log = $Log;
        }

        set_exception_handler(array(__CLASS__, 'handler'));
    }

    /**
     * Retrieves a window of lines before and after the line where the error
     * occurred so that a developer can help debug the exception
     *
     * @param integer $line
     *  The line where the error occurred.
     * @param string $file
     *  The file that holds the logic that caused the error.
     * @param integer $window
     *  The number of lines either side of the line where the error occurred
     *  to show
     * @return array
     */
    protected static function nearbyLines($line, $file, $window = 5)
    {
        if (!file_exists($file)) {
            return array();
        }

        return array_slice(file($file), ($line - 1) - $window, $window * 2, true);
    }

    /**
     * The handler function is given an Exception and will call it's render
     * function to display the Exception to a user. After calling the render
     * function, the output is displayed and then exited to prevent any further
     * logic from occurring.
     *
     * @param Exception $e
     *  The Exception object
     * @return string
     *  The result of the Exception's render function
     */
    public static function handler(Exception $e)
    {
        try {
            // Instead of just throwing an empty page, return a 404 page.
            if (self::$enabled !== true) {
                require_once(CORE . '/class.frontend.php');
                $e = new FrontendPageNotFoundException();
            };

            $exception_type = get_class($e);

            if (class_exists("{$exception_type}Handler") && method_exists("{$exception_type}Handler", 'render')) {
                $class = "{$exception_type}Handler";
            } else {
                $class = __CLASS__;
            }

            // Exceptions should be logged if they are not caught.
            if (self::$Log instanceof Log) {
                self::$Log->pushExceptionToLog($e, true);
            }

            if (!headers_sent()) {
                Page::renderStatusCode(Page::HTTP_STATUS_ERROR);
                header('Content-Type: text/html; charset=utf-8');
            }

            $output = call_user_func(array($class, 'render'), $e);

            echo $output;
            exit;
        } catch (Exception $e) {
            try {

                if (!headers_sent()) {
                    Page::renderStatusCode(Page::HTTP_STATUS_ERROR);
                    header('Content-Type: text/html; charset=utf-8');
                }

                $output = call_user_func(array('GenericExceptionHandler', 'render'), $e);

                echo $output;
                exit;
            } catch (Exception $e) {
                echo "<pre>";
                echo 'A severe error occurred whilst trying to handle an exception:' . PHP_EOL;
                echo $e->getMessage() . ' on ' . $e->getLine() . ' of file ' . $e->getFile();
                exit;
            }
        }
    }

    /**
     * Returns the path to the error-template by looking at the
     * `WORKSPACE/template/` directory, then at the `TEMPLATES`
     * directory for the convention `*.tpl`. If the template
     * is not found, false is returned
     *
     * @since Symphony 2.3
     * @param string $name
     *  Name of the template
     * @return mixed
     *  String, which is the path to the template if the template is found,
     *  false otherwise
     */
    public static function getTemplate($name)
    {
        $format = '%s/%s.tpl';

        if (file_exists($template = sprintf($format, WORKSPACE . '/template', $name))) {
            return $template;
        } elseif (file_exists($template = sprintf($format, TEMPLATE, $name))) {
            return $template;
        } else {
            return false;
        }
    }

    /**
     * The render function will take an Exception and output a HTML page
     *
     * @param Exception $e
     *  The Exception object
     * @return string
     *  An HTML string
     */
    public static function render(Exception $e)
    {
        $lines = null;

        foreach (self::nearByLines($e->getLine(), $e->getFile()) as $line => $string) {
            $lines .= sprintf(
                '<li%s><strong>%d</strong> <code>%s</code></li>',
                (($line+1) == $e->getLine() ? ' class="error"' : null),
                ++$line,
                str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', htmlspecialchars($string))
            );
        }

        $trace = null;

        foreach ($e->getTrace() as $t) {
            $trace .= sprintf(
                '<li><code><em>[%s:%d]</em></code></li><li><code>&#160;&#160;&#160;&#160;%s%s%s();</code></li>',
                (isset($t['file']) ? $t['file'] : null),
                (isset($t['line']) ? $t['line'] : null),
                (isset($t['class']) ? $t['class'] : null),
                (isset($t['type']) ? $t['type'] : null),
                $t['function']
            );
        }

        $queries = null;
        if (is_object(Symphony::Database())) {
            $debug = Symphony::Database()->debug();

            if (!empty($debug)) {
                foreach ($debug as $query) {
                    $queries .= sprintf(
                        '<li><em>[%01.4f]</em><code> %s;</code> </li>',
                        (isset($query['execution_time']) ? $query['execution_time'] : null),
                        htmlspecialchars($query['query'])
                    );
                }
            }
        }

        $html = sprintf(
            file_get_contents(self::getTemplate('fatalerror.generic')),
            ($e instanceof ErrorException ? GenericErrorHandler::$errorTypeStrings[$e->getSeverity()] : 'Fatal Error'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $lines,
            $trace,
            $queries
        );

        $html = str_replace('{SYMPHONY_URL}', SYMPHONY_URL, $html);
        $html = str_replace('{URL}', URL, $html);

        return $html;
    }
}
