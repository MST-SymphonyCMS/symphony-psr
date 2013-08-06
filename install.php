<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/symphony/boot.php';

use \SymphonyCms\Utilities\General;


use \SymphonyCms\Install\Installer;
use \SymphonyCms\Install\Updater;

ini_set('display_errors', 1);

defineSafe('TEST', 'test');

// Set the current timezone, should that not be available
// default to UTC.
if (!date_default_timezone_set(@date_default_timezone_get())) {
    date_default_timezone_set('UTC');
}

// Show PHP Info
if (isset($_REQUEST['info'])) {
    phpinfo();
    exit;
}

define('VERSION', '2.3.3');
define('INSTALL_URL', URL . '/install');

// If prompt to remove, delete the entire `/install` directory
// and then redirect to Symphony
if (isset($_GET['action']) && $_GET['action'] == 'remove') {
    General::deleteFile(__FILE__);
    redirect(SYMPHONY_URL);
}

// If Symphony is already installed, run the updater
if (file_exists(CONFIG)) {
    // System updater
    $script = Updater::instance();
} else {
    // If there's no config file, run the installer
    $script = Installer::instance();
}

$script->run();

exit;
