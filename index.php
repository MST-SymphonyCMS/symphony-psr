<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/symphony/boot.php';

use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Symphony\Frontend;

/**
 * Choose the render method for Symphony
 *
 * @param  string $mode The render mode
 * @return Administration|Frontend
 */
function renderer($mode = 'frontend')
{
    if (!in_array($mode, array('frontend', 'administration'))) {
        throw new Exception('Invalid Symphony Renderer mode specified. Must be either "frontend" or "administration".');
    }
    return ($mode == 'administration' ? Administration::instance() : Frontend::instance());
}

$renderer = (isset($_GET['mode']) && strtolower($_GET['mode']) == 'administration' ? 'administration' : 'frontend');

$output = renderer($renderer)->display(getCurrentPage());

cleanupSessionCookies();

echo $output;

exit;
