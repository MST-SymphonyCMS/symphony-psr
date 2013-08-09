<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/symphony/boot.php';

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Symphony\Frontend;

$mode = (isset($_GET['mode']) && strtolower($_GET['mode']) == 'administration' ? '\\SymphonyCms\\Symphony\\Administration' : '\\SymphonyCms\\Symphony\\Frontend');

Symphony::set('mode_class', $mode);
Symphony::initialise();

$App = new ReflectionClass(Symphony::get('mode_class'));

$output = $App::instance()->display(getCurrentPage());

cleanupSessionCookies();

echo $output;

exit;
