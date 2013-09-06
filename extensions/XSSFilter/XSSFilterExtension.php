<?php

namespace SymphonyCms\Extensions\XSSFilter;

use \SympyonyCms\Extensions\Extension;

class XSSFilterExtension extends Extension
{
    public function about()
    {
        return array(
            'name' => 'Cross-Site Scripting (XSS) Filter',
            'version' => '1.2',
            'release-date' => '2013-07-01',
            'author' => array(
                'name' => 'Symphony Team',
                'website' => 'http://getsymphony.com/',
                'email' => 'team@getsymphony.com'
            ),
            'description' => 'Protect yourself against XSS attacks in form submissions.'
        );
    }

    public function getSubscribedDelegates()
    {
        return array(
            array(
                'page' => '/blueprints/events/new/',
                'delegate' => 'AppendEventFilter',
                'callback' => 'appendEventFilter'
            ),
            array(
                'page' => '/blueprints/events/edit/',
                'delegate' => 'AppendEventFilter',
                'callback' => 'appendEventFilter'
            ),
            array(
                'page' => '/frontend/',
                'delegate' => 'EventPreSaveFilter',
                'callback' => 'eventPreSaveFilter'
            ),
        );
    }

    public function appendEventFilter(array $context)
    {
        $context['options'][] = array(
            'xss-fail',
            is_array($context['selected']) ? in_array('xss-fail', $context['selected']) : false,
            'Filter XSS: Fail if malicious input is detected'
        );
    }

    public function eventPreSaveFilter(array $context)
    {
        if (!in_array('xss-fail', $context['event']->eParamFILTERS) && !in_array('xss-remove', $context['event']->eParamFILTERS)) {
            return;
        }

        $contains_xss = false;

        // Loop over the fields to check for XSS, this loop will
        // break as soon as XSS is detected
        foreach ($context['fields'] as $field => $value) {
            if (is_array($value)) {
                if (self::detectXSSInArray($value) === false) {
                    continue;
                }

                $contains_xss = true;
                break;
            } else {
                if (self::detectXSS($value) === false) {
                    continue;
                }

                $contains_xss = true;
                break;
            }
        }

        // "fail" filter
        if (in_array('xss-fail', $context['event']->eParamFILTERS) && $contains_xss === true) {
            $context['messages'][] = array(
                'xss',
                false,
                tr("Possible XSS attack detected in submitted data")
            );
        }
    }

    /**
     * A utility function to manage nested array structures, checking
     * each value for possible XSS. Function returns boolean if XSS is
     * found.
     *
     * @param array $array
     *  An array of data to check, this can be nested arrays.
     * @return boolean
     *  True if XSS is detected, false otherwise
     */
    public static function detectXSSInArray(array $array)
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                return self::detectXSSInArray($value);
            } else {
                if (self::detectXSS($value) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Given a string, this function will determine if it potentially an
     * XSS attack and return boolean.
     *
     * @param string $string
     *  The string to run XSS detection logic on
     * @return boolean
     *  True if the given `$string` contains XSS, false otherwise.
     */
    public static function detectXSS($string)
    {
        $contains_xss = false;

        // Skip any null or non string values
        if (is_null($string) || !is_string($string)) {
            return $contains_xss;
        }

        // Keep a copy of the original string before cleaning up
        $orig = $string;

        // URL decode
        $string = urldecode($string);

        // Convert Hexadecimals
        $string = preg_replace('!(&#|\\\)[xX]([0-9a-fA-F]+);?!e', 'chr(hexdec("$2"))', $string);

        // Clean up entities
        $string = preg_replace('!(&#0+[0-9]+)!', '$1;', $string);

        // Decode entities
        $string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');

        // Strip whitespace characters
        $string = preg_replace('!\s!', '', $string);

        // Set the patterns we'll test against
        $patterns = array(
            // Match any attribute starting with "on" or xmlns
            '#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>?#iUu',

            // Match javascript:, livescript:, vbscript: and mocha: protocols
            '!((java|live|vb)script|mocha|feed|data):(\w)*!iUu',
            '#-moz-binding[\x00-\x20]*:#u',

            // Match style attributes
            '#(<[^>]+[\x00-\x20\"\'\/])style=[^>]*>?#iUu',

            // Match unneeded tags
            '#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>?#i'
        );

        foreach ($patterns as $pattern) {
            // Test both the original string and clean string
            if (preg_match($pattern, $string) || preg_match($pattern, $orig)) {
                $contains_xss = true;
            }

            if ($contains_xss === true) {
                return true;
            }
        }

        return false;
    }
}
