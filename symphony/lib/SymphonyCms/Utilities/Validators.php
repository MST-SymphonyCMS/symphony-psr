<?php

namespace SymphonyCms\Utilities;

/**
 * Validators
 *
 * @package SymphonyCms
 */
class Validators
{
    public static $number = '/^-?(?:\d+(?:\.\d+)?|\.\d+)$/i';

    public static $email = '/^\w(?:\.?[\w%+-]+)*@\w(?:[\w-]*\.)+?[a-z]{2,}$/i';

    public static $uri = '/^[^\s:\/?#]+:(?:\/{2,3})?[^\s.\/?#]+(?:\.[^\s.\/?#]+)*(?:\/?[^\s?#]*\??[^\s?#]*(#[^\s#]*)?)?$/';

    public static $image = '/\.(?:bmp|gif|jpe?g|png)$/i';

    public static $document = '/\.(?:docx?|pdf|rtf|txt)$/i';

    /**
     * Rules grouped by type of string
     *
     * @return array
     */
    public static function string()
    {
        return array(
            self::$number,
            self::$email,
            self::$uri
        );
    }

    /**
     * Rules grouped by type of upload
     *
     * @return array
     */
    public static function upload()
    {
        return array(
            Validators::$image,
            Validators::$document
        );
    }
}
