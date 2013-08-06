<?php

namespace SymphonyCms\Symphony;

use \SymphonyCms\Symphony;
use \SymphonyCms\Pages\FrontendPage;

/**
 * The Frontend class is the renderer that is used to display FrontendPage's.
 * A `FrontendPage` is one that is setup in Symphony and it's output is generated
 * by using XML and XSLT
 *
 * @package SymphonyCms
 * @subpackage Symphony
 */
class Frontend extends Symphony
{
    /**
     * An instance of the FrontendPage class
     * @var FrontendPage
     */
    private static $_page;

    /**
     * This function returns an instance of the Frontend
     * class. It is the only way to create a new Frontend, as
     * it implements the Singleton interface
     *
     * @return Frontend
     */
    public static function instance()
    {
        if (!(self::$instance instanceof Frontend)) {
            self::$instance = new Frontend;
        }

        return self::$instance;
    }

    /**
     * The constructor for Frontend calls the parent Symphony constructor.
     *
     * @see core.Symphony#__construct()
     */
    protected function __construct()
    {
        parent::__construct();

        $this->_env = array();
    }

    /**
     * Accessor for `$_page`
     *
     * @return FrontendPage
     */
    public static function Page()
    {
        return self::$_page;
    }

    /**
     * Overrides the Symphony `isLoggedIn()` function to allow Authors
     * to become logged into the frontend when `$_REQUEST['auth-token']`
     * is present. This logs an Author in using the loginFromToken function.
     * This function allows the use of 'admin' type pages, where a Frontend
     * page requires that the viewer be a Symphony Author
     *
     * @see core.Symphony#loginFromToken()
     * @see core.Symphony#isLoggedIn()
     * @return boolean
     */
    public function isLoggedIn()
    {
        if (isset($_REQUEST['auth-token']) && $_REQUEST['auth-token'] && strlen($_REQUEST['auth-token']) == 8) {
            return $this->loginFromToken($_REQUEST['auth-token']);
        }

        return parent::isLoggedIn();
    }

    /**
     * Called by index.php, this function is responsible for rendering the current
     * page on the Frontend. One delegate is fired, `FrontendInitialised`
     *
     * @uses FrontendInitialised
     * @see \SymphonyCms\Utilities\getCurrentPage()
     * @param string $page
     *  The result of \SymphonyCms\Utilities\getCurrentPage, which returns the `$_GET['symphony-page']`
     * @return string
     *  The HTML of the page to return
     */
    public function display($page)
    {
        self::$_page = new FrontendPage;

        /**
         * `FrontendInitialised` is fired just after the `$_page` variable has been
         * created with an instance of the `FrontendPage` class. This delegate is
         * fired just before the `FrontendPage->generate()`.
         *
         * @delegate FrontendInitialised
         * @param string $context
         *  '/frontend/'
         */
        Symphony::ExtensionManager()->notifyMembers('FrontendInitialised', '/frontend/');

        $output = self::$_page->generate($page);

        return $output;
    }
}
