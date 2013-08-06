<?php

require_once __DIR__.'/functions.php';

/**
 * @package boot
 */

/**
 * The filesystem document root of the installation
 * @var string
 */
defineSafe('DOCROOT', rtrim(dirname(__DIR__), '\\/'));

/**
 * Path info
 * @var string
 */
defineSafe('PATH_INFO', isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : null);

/**
 * The domain path for the installation
 * @var string
 */
defineSafe('DOMAIN_PATH', dirname(rtrim($_SERVER['PHP_SELF'], PATH_INFO)));

/**
 * The domain for the installation
 * @var string
 */
defineSafe('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . DOMAIN_PATH, '\\/'));

/**
 * Used to determine if Symphony has been loaded, useful to prevent
 * files from being accessed directly.
 * @var boolean
 */
defineSafe('__IN_SYMPHONY__', true);

/**
 * The filesystem path to the `manifest` folder
 * @var string
 */
defineSafe('MANIFEST', DOCROOT . '/manifest');

/**
 * The filesystem path to the `extensions` folder
 * @var string
 */
defineSafe('EXTENSIONS', DOCROOT . '/extensions');

/**
 * The filesystem path to the `workspace` folder
 * @var string
 */
defineSafe('WORKSPACE', DOCROOT . '/workspace');

/**
 * The filesystem path to the `symphony` folder
 * @var string
 */
defineSafe('SYMPHONY', DOCROOT . '/symphony');

/**
 * The filesystem path to the `lib` folder which is contained within
 * the `symphony` folder.
 * @var string
 */
defineSafe('LIBRARY', SYMPHONY . '/lib');

/**
 * The filesystem path to the `assets` folder which is contained within
 * the `symphony` folder.
 * @var string
 */
defineSafe('ASSETS', SYMPHONY . '/assets');

/**
 * The filesystem path to the `content` folder which is contained within
 * the `symphony` folder.
 * @var string
 */
defineSafe('CONTENT', SYMPHONY . '/content');

/**
 * The filesystem path to the `template` folder which is contained within
 * the `symphony` folder.
 * @var string
 */
defineSafe('TEMPLATE', SYMPHONY . '/template');

/**
 * The filesystem path to the `utilities` folder which is contained within
 * the `workspace` folder.
 * @var string
 */
defineSafe('UTILITIES', WORKSPACE . '/utilities');

/**
 * The filesystem path to the `data-sources` folder which is contained within
 * the `workspace` folder.
 * @var string
 */
defineSafe('DATASOURCES', WORKSPACE . '/data-sources');

/**
 * The filesystem path to the `events` folder which is contained within
 * the `workspace` folder.
 * @var string
 */
defineSafe('EVENTS', WORKSPACE . '/events');

/**
 * The filesystem path to the `text-formatters` folder which is contained within
 * the `workspace` folder.
 * @var string
 */
defineSafe('TEXTFORMATTERS', WORKSPACE . '/text-formatters');

/**
 * The filesystem path to the `pages` folder which is contained within
 * the `workspace` folder.
 * @var string
 */
defineSafe('PAGES', WORKSPACE . '/pages');

/**
 * The filesystem path to the `cache` folder which is contained within
 * the `manifest` folder.
 * @var string
 */
defineSafe('CACHE', MANIFEST . '/cache');

/**
 * The filesystem path to the `tmp` folder which is contained within
 * the `manifest` folder.
 * @var string
 */
defineSafe('TMP', MANIFEST . '/tmp');

/**
 * The filesystem path to the `logs` folder which is contained within
 * the `manifest` folder. The default Symphony Log file is saved at this
 * path.
 * @var string
 */
defineSafe('LOGS', MANIFEST . '/logs');

/**
 * The filesystem path to the `main` file which is contained within
 * the `manifest/logs` folder. This is the default Symphony log file.
 * @var string
 */
defineSafe('ACTIVITY_LOG', LOGS . '/main');

/**
 * The filesystem path to the `config.php` file which is contained within
 * the `manifest` folder. This holds all the Symphony configuration settings
 * for this install.
 * @var string
 */
defineSafe('CONFIG', MANIFEST . '/config.php');

/**
 * The filesystem path to the `boot` folder which is contained within
 * the `symphony/lib` folder.
 * @var string
 */
defineSafe('BOOT', LIBRARY . '/boot');

/**
 * The filesystem path to the `core` folder which is contained within
 * the `symphony/lib` folder.
 * @var string
 */
defineSafe('CORE', LIBRARY . '/core');

/**
 * The filesystem path to the `lang` folder which is contained within
 * the `symphony/lib` folder. By default, the Symphony install comes with
 * an english language translation.
 * @var string
 */
defineSafe('LANG', LIBRARY . '/lang');

/**
 * The filesystem path to the `toolkit` folder which is contained within
 * the `symphony/lib` folder.
 * @var string
 */
defineSafe('TOOLKIT', LIBRARY . '/toolkit');

/**
 * The filesystem path to the `interface` folder which is contained within
 * the `symphony/lib` folder.
 * @since Symphony 2.3
 * @var string
 */
defineSafe('FACE', LIBRARY . '/interface');

/**
 * The filesystem path to the `email-gateways` folder which is contained within
 * the `symphony/lib/toolkit` folder.
 * @since Symphony 2.2
 * @var string
 */
defineSafe('EMAILGATEWAYS', TOOLKIT . '/email-gateways');

/**
 * Used as a default seed, this returns the time in seconds that Symphony started
 * to load. Most profiling runs use this as a benchmark.
 * @var float
 */
defineSafe('STARTTIME', precisionTimer());

/**
 * Returns the number of seconds that represent two weeks.
 * @var integer
 */
defineSafe('TWO_WEEKS', (60*60*24*14));

/**
 * Returns the environmental variable if HTTPS is in use.
 * @var string|boolean
 */
defineSafe('HTTPS', getenv('HTTPS'));

/**
 * Returns the current host, ie. google.com
 * @var string
 */
defineSafe('HTTP_HOST', getenv('HTTP_HOST'));

/**
 * Returns the IP address of the machine that is viewing the current page.
 * @var string
 */
defineSafe('REMOTE_ADDR', getenv('REMOTE_ADDR'));

/**
 * Returns the User Agent string of the browser that is viewing the current page
 * @var string
 */
defineSafe('HTTP_USER_AGENT', getenv('HTTP_USER_AGENT'));

/**
 * If HTTPS is on, `__SECURE__` will be set to true, otherwise false. Use union of
 * the `HTTPS` environmental variable and the X-Forwarded-Proto header to allow
 * downstream proxies to inform the webserver of secured downstream connections
 * @var string|boolean
 */
defineSafe(
    '__SECURE__',
    (HTTPS == 'on' ||
        isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
);

/**
 * The base URL of this Symphony install, minus the symphony path.
 * @var string
 */
defineSafe('URL', 'http' . (defined('__SECURE__') && __SECURE__ ? 's' : '') . '://' . DOMAIN);

/**
 * Returns the URL + /symphony. This should be used whenever the a developer
 * wants to link to the Symphony root
 * @since Symphony 2.2
 * @var string
 */
defineSafe('SYMPHONY_URL', URL . '/symphony');

/**
 * Returns the folder name for Symphony as an application
 * @since Symphony 2.3.2
 * @var string
 */
defineSafe('APP_URL', URL . '/symphony');
