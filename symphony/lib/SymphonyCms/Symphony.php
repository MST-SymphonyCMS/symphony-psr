<?php

namespace SymphonyCms;

use \DateTime;
use \DateTimeZone;
use \DirectoryIterator;
use \Exception;
use \ReflectionClass;
use \StdClass;

use \Manneken\Container\StaticContainer;

use \SymphonyCms\Exceptions\DatabaseException;
use \SymphonyCms\Exceptions\GenericErrorHandler;
use \SymphonyCms\Exceptions\GenericExceptionHandler;
use \SymphonyCms\Exceptions\SymphonyErrorPage;

use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Symphony\Configuration;
use \SymphonyCms\Symphony\Cookie;
use \SymphonyCms\Symphony\DateTimeObj;
use \SymphonyCms\Symphony\Frontend;
use \SymphonyCms\Symphony\Log;

use \SymphonyCms\Toolkit\AuthorManager;
use \SymphonyCms\Toolkit\Cryptography;
use \SymphonyCms\Extensions\ExtensionManager;
use \SymphonyCms\Toolkit\Lang;
use \SymphonyCms\Toolkit\MySQL;
use \SymphonyCms\Toolkit\Page;
use \SymphonyCms\Toolkit\PageManager;
use \SymphonyCms\Toolkit\Profiler;

use \SymphonyCms\Utilities\General;

/**
 * The Symphony class is an IoC container.
 * It provides the glue that forms the Symphony CMS and initialises the toolkit classes.
 *
 * @package SymphonyCms
 */
class Symphony extends StaticContainer
{
    /**
     * The current page namespace, used for translations
     * @since Symphony 2.3
     * @var string
     */
    private static $namespace = false;

    /**
     * A previous exception that has been fired. Defaults to null.
     * @since Symphony 2.3.2
     * @var Exception
     */
    private static $exception = null;

    /**
     * The Symphony container initialises the class instances of Symphony.
     * It will set the DateTime settings, define new date constants and initialise
     * the correct Language for the currently logged in Author. The initialiser loads in
     * the initial Configuration values from the `CONFIG` file
     */
    public static function initialise()
    {
        self::initialiseProfiler();
        self::initialiseEngine();
        self::initialiseConfiguration();

        $configuration = self::get('Configuration');

        DateTimeObj::setSettings($configuration->get('region'));

        // Initialize language management
        Lang::initialize();

        self::initialiseLog();

        GenericExceptionHandler::initialise(self::get('Log'));
        GenericErrorHandler::initialise(self::get('Log'));

        self::initialiseExtensionManager();
        self::initialiseDatabase();
        self::initialiseCookie();

        self::get('ExtensionManager')->getSubscriptions();

        // If the user is not a logged in Author, turn off the verbose error messages.
        if (!self::isLoggedIn() && !self::has('Author')) {
            GenericExceptionHandler::$enabled = false;
        }

        // Set system language
        Lang::set(self::get('Configuration')->get('lang', 'symphony'));
    }

    /**
     * Initialise an instance of the `Profiler` into the Symphony container
     */
    public static function initialiseProfiler()
    {
        self::singleton(
            'Profiler',
            function ($con) {
                $class = $con->get('profiler_class');
                return new $class;
            }
        );

        self::get('Profiler')->sample('Engine Initialisation');
    }

    /**
     * Initialise an instance of either `Frontend` or `Administration` into the Symphony container
     */
    public static function initialiseEngine()
    {
        self::singleton(
            'Engine',
            function ($con) {
                $class = $con->get('mode_class');

                return new $class;
            }
        );
    }

    /**
     * Setter for `Configuration`. This function initialises the configuration object into the container, and populate its properties based on the given $array.
     *
     * @since Symphony 2.3
     * @param array $data
     *  An array of settings to be stored into the Configuration object
     */
    public static function initialiseConfiguration(array $data = array())
    {
        if (empty($data)) {
            $data = include CONFIG;
        }

        self::singleton(
            'Configuration',
            function ($con) use ($data) {
                $class = $con->get('configuration_class');

                $configuration = new $class(true);
                $configuration->setArray($data);

                return $configuration;
            }
        );
    }

    /**
     * Sets defines that are based on `Configuration` values
     */
    public static function setDefines()
    {
        defineSafe('__SYM_DATE_FORMAT__', self::get('Configuration')->get('date_format', 'region'));
        defineSafe('__SYM_TIME_FORMAT__', self::get('Configuration')->get('time_format', 'region'));
        defineSafe('__SYM_DATETIME_FORMAT__', __SYM_DATE_FORMAT__ . self::get('Configuration')->get('datetime_separator', 'region') . __SYM_TIME_FORMAT__);
    }

    /**
     * Setter for `Log`. This function uses the configuration settings in the 'log' group in the `Configuration` to create an instance in the container. Date formatting options are also retrieved from the configuration.
     *
     * @param string $filename (optional)
     *  The file to write the log to, if omitted this will default to `ACTIVITY_LOG`
     */
    public static function initialiseLog($filename = null)
    {
        if (is_null($filename)) {
            $filename = ACTIVITY_LOG;
        }

        if (!self::has('Log')) {
            self::singleton(
                'Log',
                function ($con) use ($filename) {
                    $class = $con->get('log_class');

                    return new $class($filename);
                }
            );
        }

        $log = self::get('Log');
        $config = self::get('Configuration');

        $log->setArchive(($config->get('archive', 'log') == '1' ? true : false));
        $log->setMaxSize(intval($config->get('maxsize', 'log')));
        $log->setDateTimeFormat($config->get('date_format', 'region') . ' ' . $config->get('time_format', 'region'));

        if ($log->open($log::APPEND, $config->get('write_mode', 'file')) == 1) {
            $log->initialise('Symphony Log');
        }
    }

    /**
     * Setter for `ExtensionManager` This function adds an instance into the container. If for some reason this fails, a Symphony Error page will be thrown
     */
    public static function initialiseExtensionManager()
    {
        if (!self::has('ExtensionManager')) {
            self::singleton(
                'ExtensionManager',
                function ($con) {
                    $class = $con->get('extension_manager_class');

                    return new $class;
                }
            );
        }

        if (!self::has('ExtensionManager')) {
            self::throwCustomError(tr('Error creating Symphony extension manager.'));
        }
    }

    /**
     * This will initialise the Database class into the container, and attempt to create a connection using the connection details provided in the Symphony configuration. If any errors occur whilst doing so, a Symphony Error Page is displayed.
     *
     * @return boolean
     *  This function will return true if the `$Database` was
     *  initialised successfully.
     */
    public static function initialiseDatabase()
    {
        self::setDatabase();

        $database = self::get('Database');
        $configuration = self::get('Configuration');

        $details = $configuration->get('database');

        try {
            if (!$database->connect($details['host'], $details['user'], $details['password'], $details['port'], $details['db'])) {
                return false;
            }

            if (!$database->isConnected()) {
                return false;
            }

            $database->setPrefix($details['tbl_prefix']);
            $database->setCharacterEncoding();
            $database->setCharacterSet();

            // Set Timezone, need to convert human readable, ie. Australia/Brisbane to be +10:00
            // @see https://github.com/symphonycms/symphony-2/issues/1726
            $timezone = $configuration->get('timezone', 'region');
            $symphony_date = new DateTime('now', new DateTimeZone($timezone));

            // MySQL wants the offset to be in the format +/-H:I, getOffset returns offset in seconds
            $utc = new DateTime('now ' . $symphony_date->getOffset() . ' seconds', new DateTimeZone("UTC"));

            $offset = $symphony_date->diff($utc)->format('%R%H:%I');

            $database->setTimeZone($offset);

            if ($configuration->get('query_caching', 'database') == 'off') {
                $database->disableCaching();
            } elseif ($configuration->get('query_caching', 'database') == 'on') {
                $database->enableCaching();
            }
        } catch (DatabaseException $e) {
            $this->throwCustomError(
                $e->getDatabaseErrorCode() . ': ' . $e->getDatabaseErrorMessage(),
                tr('Symphony Database Error'),
                Page::HTTP_STATUS_ERROR,
                'database',
                array(
                    'error' => $e,
                    'message' => tr('There was a problem whilst attempting to establish a database connection. Please check all connection information is correct.') . ' ' . tr('The following error was returned:')
                )
            );
        }

        return true;
    }

    /**
     * Setter for `Database`, accepts a Database object. If `$database`
     * is omitted, this function will set `$Database` to be of the `MySQL`
     * class.
     *
     * @since Symphony 2.3
     * @param StdClass $database (optional)
     *  The class to handle all Database operations, if omitted this function
     *  will set `self::$Database` to be an instance of the `MySQL` class.
     */
    public static function setDatabase(StdClass $database = null)
    {
        if (!self::has('Database')) {
            self::singleton(
                'Database',
                function ($con) use ($database) {
                    $class = (is_null($database) ? $con->get('database_class') : $database);

                    return new $class;
                }
            );
        }
    }

    /**
     * Setter for `Cookie`. This will use PHP's parse_url
     * function on the current URL to set a cookie using the cookie_prefix
     * defined in the Symphony configuration. The cookie will last two
     * weeks.
     *
     * This function also defines two constants, `__SYM_COOKIE_PATH__`
     * and `__SYM_COOKIE_PREFIX__`.
     */
    public static function initialiseCookie()
    {
        $configuration = self::get('Configuration');

        $cookie_path = @parse_url(URL, PHP_URL_PATH);
        $cookie_path = '/' . trim($cookie_path, '/');

        defineSafe('__SYM_COOKIE_PATH__', $cookie_path);
        defineSafe('__SYM_COOKIE_PREFIX__', $configuration->get('cookie_prefix', 'symphony'));

        if (!self::has('Cookie')) {
            self::singleton(
                'Cookie',
                function ($con) {
                    $class = $con->get('cookie_class');

                    return new $class(__SYM_COOKIE_PREFIX__, TWO_WEEKS, __SYM_COOKIE_PATH__);
                }
            );
        }
    }

    /**
     * Attempts to log an Author in given a username and password.
     * If the password is not hashed, it will be hashed using the sha1
     * algorithm. The username and password will be sanitized before
     * being used to query the Database. If an Author is found, they
     * will be logged in and the sanitized username and password (also hashed)
     * will be saved as values in the `$Cookie`.
     *
     * @see toolkit.General#hash()
     * @param string $username
     *  The Author's username. This will be sanitized before use.
     * @param string $password
     *  The Author's password. This will be sanitized and then hashed before use
     * @param boolean $isHash
     *  If the password provided is already hashed, setting this parameter to
     *  true will stop it becoming rehashed. By default it is false.
     * @return boolean
     *  True if the Author was logged in, false otherwise
     */
    public static function login($username, $password, $isHash = false)
    {
        if (!self::has('Author')) {
            $database = self::get('Database');
            $username = $database->cleanValue($username);
            $password = $database->cleanValue($password);

            if (strlen(trim($username)) > 0 && strlen(trim($password)) > 0) {
                $authormanager = self::get('author_manager_class');

                $author = $authormanager::fetch(
                    'id',
                    'ASC',
                    1,
                    null,
                    sprintf(
                        "`username` = '%s'",
                        $username
                    )
                );

                if (!empty($author) && Cryptography::compare($password, current($author)->get('password'), $isHash)) {
                    $author = current($author);

                    self::singleton(
                        'Author',
                        function ($con) use ($author) {
                            return $author;
                        }
                    );

                    $author = self::get('Author');

                    // Only migrate hashes if there is no update available as the update might change the tbl_authors table.
                    if (self::isUpgradeAvailable() === false && Cryptography::requiresMigration($author->get('password'))) {
                        $author->set('password', Cryptography::hash($password));
                        $database->update(
                            array(
                                'password' => $author->get('password')
                            ),
                            'tbl_authors',
                            " `id` = '" . $author->get('id') . "'"
                        );
                    }

                    $cookie = self::get('Cookie');

                    $cookie->set('username', $username);
                    $cookie->set('pass', $author->get('password'));

                    $database->update(
                        array(
                            'last_seen' => DateTimeObj::get('Y-m-d H:i:s')
                        ),
                        'tbl_authors',
                        sprintf(" `id` = %d", $this->Author->get('id'))
                    );

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Symphony allows Authors to login via the use of tokens instead of
     * a username and password. A token is derived from concatenating the
     * Author's username and password and applying the sha1 hash to
     * it, from this, a portion of the hash is used as the token. This is a useful
     * feature often used when setting up other Authors accounts or if an
     * Author forgets their password.
     *
     * @param string $token
     *  The Author token, which is a portion of the hashed string concatenation
     *  of the Author's username and password
     * @return boolean
     *  True if the Author is logged in, false otherwise
     */
    public static function loginFromToken($token)
    {
        $database = self::get('Database');
        $token = $database->cleanValue($token);

        if (strlen(trim($token)) == 0) {
            return false;
        }

        if (strlen($token) == 6) {
            $row = $database->fetchRow(
                0,
                sprintf(
                    "SELECT `a`.`id`, `a`.`username`, `a`.`password`
                    FROM `tbl_authors` AS `a`, `tbl_forgotpass` AS `f`
                    WHERE `a`.`id` = `f`.`author_id`
                    AND `f`.`expiry` > '%s'
                    AND `f`.`token` = '%s'
                    LIMIT 1",
                    DateTimeObj::getGMT('c'),
                    $token
                )
            );

            $database->delete('tbl_forgotpass', " `token` = '{$token}' ");
        } else {
            $row = $database->fetchRow(
                0,
                sprintf(
                    "SELECT `id`, `username`, `password`
                    FROM `tbl_authors`
                    WHERE SUBSTR(%s(CONCAT(`username`, `password`)), 1, 8) = '%s'
                    AND `auth_token_active` = 'yes'
                    LIMIT 1",
                    'SHA1',
                    $token
                )
            );
        }

        if ($row) {
            self::singleton(
                'Author',
                function ($con) use ($row) {
                    $authormanager = $con::get('author_manager_class');

                    return $authormanager::fetchByID($row['id']);
                }
            );

            $cookie = $con::get('Cookie');
            $cookie->set('username', $row['username']);
            $cookie->set('pass', $row['password']);

            $database->update(
                array(
                    'last_seen' => DateTimeObj::getGMT('Y-m-d H:i:s')
                ),
                'tbl_authors',
                " `id` = '{$row['id']}'"
            );

            return true;
        }

        return false;
    }

    /**
     * This function will destroy the currently logged in `$Author`
     * session, essentially logging them out.
     *
     * @see core.Cookie#expire()
     */
    public static function logout()
    {
        self::get('Cookie')->expire();
    }

    /**
     * This function determines whether an there is a currently logged in
     * Author for Symphony by using the `$Cookie`'s username
     * and password. If an Author is found, they will be logged in, otherwise
     * the `$Cookie` will be destroyed.
     *
     * @see core.Cookie#expire()
     */
    public static function isLoggedIn()
    {
        // Ensures that we're in the real world.. Also reduces three queries from database
        // We must return true otherwise exceptions are not shown
        if (is_null(self::$instance)) {
            return true;
        }

        if (self::has('Author')) {
            return true;
        } else {
            $database = self::get('Database');
            $cookie = self::get('Cookie');

            $username = $database->cleanValue($cookie->get('username'));
            $password = $database->cleanValue($cookie->get('pass'));

            if (strlen(trim($username)) > 0 && strlen(trim($password)) > 0) {
                $authormanager = self::get('author_manager_class');

                $author = $authormanager::fetch(
                    'id',
                    'ASC',
                    1,
                    null,
                    sprintf("`username` = '%s'", $username)
                );

                if (!empty($author) && Cryptography::compare($password, current($author)->get('password'), true)) {
                    $author = current($author);

                    self::singleton(
                        'Author',
                        function ($con) use ($author) {
                            return $author;
                        }
                    );

                    $author = self::get('Author');

                    $database->update(
                        array('last_seen' => DateTimeObj::get('Y-m-d H:i:s')),
                        'tbl_authors',
                        sprintf(" `id` = %d", $author->get('id'))
                    );

                    // Only set custom author language in the backend
                    if (class_exists('\\SymphonyCms\\Symphony\\Administration')) {
                        Lang::set($author->get('language'));
                    }

                    return true;
                }
            }

            self::get('Cookie')->expire();

            return false;
        }
    }

    /**
     * Returns the most recent version found in the `/install/migrations` folder.
     * Returns a version string to be used in `version_compare()` if an updater
     * has been found. Returns `false` otherwise.
     *
     * @since Symphony 2.3.1
     * @return mixed
     */
    public static function getMigrationVersion()
    {
        if (self::isInstallerAvailable()) {
            $migrations = new DirectoryIterator(__DIR__.'/Install/Migrations');
            $migration_file = end($migrations);
            $migration_class = 'SymphonyCms\\Install\\Migrations\\' . $migration_file;

            if (class_exists($migration_class)) {
                return call_user_func(array($migration_class, 'getVersion'));
            }
        } else {
            return false;
        }
    }

    /**
     * Checks if an update is available and applicable for the current installation.
     *
     * @since Symphony 2.3.1
     * @return boolean
     */
    public static function isUpgradeAvailable()
    {
        if (self::isInstallerAvailable()) {
            $migration_version = self::getMigrationVersion();
            $current_version = self::get('Configuration')->get('version', 'symphony');
            return version_compare($current_version, $migration_version, '<');
        } else {
            return false;
        }
    }

    /**
     * Checks if the installer/upgrader is available.
     *
     * @since Symphony 2.3.1
     * @return boolean
     */
    public static function isInstallerAvailable()
    {
        return file_exists(DOCROOT . '/install.php');
    }

    /**
     * A wrapper for throwing a new Symphony Error page.
     *
     * @deprecated @since Symphony 2.3.2
     *
     * @see `throwCustomError`
     * @param string $heading
     *  A heading for the error page
     * @param string|XMLElement $message
     *  A description for this error, which can be provided as a string
     *  or as an XMLElement.
     * @param string $template
     *  A string for the error page template to use, defaults to 'generic'. This
     *  can be the name of any template file in the `TEMPLATES` directory.
     *  A template using the naming convention of `tpl.*.php`.
     * @param array $additional
     *  Allows custom information to be passed to the Symphony Error Page
     *  that the template may want to expose, such as custom Headers etc.
     */
    public static function customError($heading, $message, $template = 'generic', array $additional = array())
    {
        self::throwCustomError($message, $heading, Page::HTTP_STATUS_ERROR, $template, $additional);
    }

    /**
     * A wrapper for throwing a new Symphony Error page.
     *
     * This methods sets the `GenericExceptionHandler::$enabled` value to `true`.
     *
     * @see core.SymphonyErrorPage
     * @param string|XMLElement $message
     *  A description for this error, which can be provided as a string
     *  or as an XMLElement.
     * @param string $heading
     *  A heading for the error page
     * @param integer $status
     *  Properly sets the HTTP status code for the response. Defaults to
     *  `Page::HTTP_STATUS_ERROR`. Use `Page::HTTP_STATUS_XXX` to set this value.
     * @param string $template
     *  A string for the error page template to use, defaults to 'generic'. This
     *  can be the name of any template file in the `TEMPLATES` directory.
     *  A template using the naming convention of `tpl.*.php`.
     * @param array $additional
     *  Allows custom information to be passed to the Symphony Error Page
     *  that the template may want to expose, such as custom Headers etc.
     */
    public static function throwCustomError($message, $heading = 'Symphony Fatal Error', $status = Page::HTTP_STATUS_ERROR, $template = 'generic', array $additional = array())
    {
        GenericExceptionHandler::$enabled = true;

        throw new SymphonyErrorPage($message, $heading, $template, $additional, $status);
    }

    /**
     * Setter accepts a previous Exception. Useful for determining the context
     * of a current exception (ie. detecting recursion).
     *
     * @since Symphony 2.3.2
     * @param Exception $ex
     */
    public static function setException(Exception $exception)
    {
        self::$exception = $exception;
    }

    /**
     * Accessor for `$this->exception`.
     *
     * @since Symphony 2.3.2
     * @return Exception|null
     */
    public static function getException()
    {
        return self::$exception;
    }

    /**
     * Returns the page namespace based on the current URL.
     * A few examples:
     *
     * /login
     * /publish
     * /blueprints/datasources
     * [...]
     * /extension/$extension_name/$page_name
     *
     * This method is especially useful in couple with the translation function.
     *
     * @see toolkit#tr()
     * @return string
     *  The page namespace, without any action string (e.g. "new", "saved") or
     *  any value that depends upon the single setup (e.g. the section handle in
     *  /publish/$handle)
     */
    public static function getPageNamespace()
    {
        if (self::$namespace !== false) {
            return self::$namespace;
        }

        $page = getCurrentPage();

        if (!is_null($page)) {
            $page = trim($page, '/');
        }

        if (substr($page, 0, 7) == 'publish') {
            self::$namespace = '/publish';
        } elseif (empty($page) && isset($_REQUEST['mode'])) {
            self::$namespace = '/login';
        } elseif (empty($page)) {
            self::$namespace = null;
        } else {
            $bits = explode('/', $page);

            if ($bits[0] == 'extension') {
                self::$namespace = sprintf('/%s/%s/%s', $bits[0], $bits[1], $bits[2]);
            } else {
                self::$namespace =  sprintf('/%s/%s', $bits[0], isset($bits[1]) ? $bits[1] : '');
            }
        }

        return self::$namespace;
    }
}
