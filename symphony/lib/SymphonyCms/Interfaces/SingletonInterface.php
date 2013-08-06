<?php

namespace SymphonyCms\Interfaces;

/**
 * @package core
 */

/**
 * The Singleton interface contains one function, `instance()`,
 * the will return an instance of an Object that implements this
 * interface.
 * @package SymphonyCms
 * @subpackage Interfaces
 */
interface SingletonInterface
{
    public static function instance();
}
