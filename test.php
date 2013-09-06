<?php

require_once __DIR__.'/vendor/autoload.php';

use \SymphonyCms\Utilities\Container;

$ymphony = new Container();

$ymphony->test = 'pants banjo';

var_dump($ymphony->test, $ymphony['test']);
