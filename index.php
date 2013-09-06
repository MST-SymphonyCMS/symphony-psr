<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/symphony/boot.php';

use \SymphonyCms\Symphony;

// Get and set all of the classes into the container that are needed to initialise Symphony
$classes = include MANIFEST.'/config_classes.php';
foreach ($classes as $key => $class) {
    Symphony::set($key, $class);
}

// Pick the correct operation mode, set that into the container
$mode = (isset($_GET['mode']) && strtolower($_GET['mode']) == 'administration' ? '\\SymphonyCms\\Symphony\\Administration' : '\\SymphonyCms\\Symphony\\Frontend');
Symphony::set('mode_class', $mode);

// Initialise the container
Symphony::initialise();

// Generate the output
$output = Symphony::get('Engine')->display(getCurrentPage());

cleanupSessionCookies();

echo $output;

exit;
