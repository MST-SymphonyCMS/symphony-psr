<?php

namespace SymphonyCms\Exceptions;

use \Exception;

/**
 * `FrontendPageNotFoundException` extends a default Exception, it adds nothing
 * but allows a different Handler to be used to render the Exception
 *
 * @see core.FrontendPageNotFoundExceptionHandler
 */
class FrontendPageNotFoundException extends Exception
{
    /**
     * The constructor for `FrontendPageNotFoundException` sets the default
     * error message and code for Logging purposes
     */
    public function __construct()
    {
        parent::__construct();

        $pagename = getCurrentPage();

        if (empty($pagename)) {
            $this->message = tr('The page you requested does not exist.');
        } else {
            $this->message = tr('The page you requested, %s, does not exist.', array('<code>' . $pagename . '</code>'));
        }

        $this->code = E_USER_NOTICE;
    }
}
