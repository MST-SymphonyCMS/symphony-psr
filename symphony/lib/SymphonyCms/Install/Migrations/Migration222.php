<?php

namespace SymphonyCms\Install\Migrations;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Exceptions\DatabaseException;
use \SymphonyCms\Install\Migration;

/**
 * Migration to 2.2.2
 *
 * @package SymphonyCms
 * @subpackage Install
 */
class Migration222 extends Migration
{
    public static function run($function, $existing_version = null)
    {
        self::$existing_version = $existing_version;

        try {
            $canProceed = self::$function();

            return ($canProceed === false) ? false : true;
        } catch (DatabaseException $e) {
            Symphony::Log()->writeToLog('Could not complete upgrading. MySQL returned: ' . $e->getDatabaseErrorCode() . ': ' . $e->getDatabaseErrorMessage(), E_ERROR, true);

            return false;
        } catch(Exception $e) {
            Symphony::Log()->writeToLog('Could not complete upgrading because of the following error: ' . $e->getMessage(), E_ERROR, true);

            return false;
        }
    }

    public static function getVersion()
    {
        return '2.2.2';
    }

    public static function getReleaseNotes()
    {
        return 'http://getsymphony.com/download/releases/version/2.2.2/';
    }

    public static function upgrade()
    {
        // 2.2.2 Beta 1
        if (version_compare(self::$existing_version, '2.2.2 Beta 1', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2.2 Beta 1', 'symphony');

            // Rename old variations of the query_caching configuration setting
            if (Symphony::get('Configuration')->get('disable_query_caching', 'database')) {
                $value = (Symphony::get('Configuration')->get('disable_query_caching', 'database') == "no") ? "on" : "off";

                Symphony::get('Configuration')->set('query_caching', $value, 'database');
                Symphony::get('Configuration')->remove('disable_query_caching', 'database');
            }

            // Add Session GC collection as a configuration parameter
            Symphony::get('Configuration')->set('session_gc_divisor', '10', 'symphony');

            // Save the manifest changes
            Symphony::get('Configuration')->write();
        }

        // 2.2.2 Beta 2
        if (version_compare(self::$existing_version, '2.2.2 Beta 2', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2.2 Beta 2', 'symphony');
            try {
                // Change Textareas to be MEDIUMTEXT columns
                $textarea_tables = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_textarea`");

                foreach ($textarea_tables as $field) {
                    Symphony::get('Database')->query(
                        sprintf(
                            "ALTER TABLE `tbl_entries_data_%d` CHANGE `value` `value` MEDIUMTEXT, CHANGE `value_formatted` `value_formatted` MEDIUMTEXT",
                            $field
                        )
                    );
                    Symphony::get('Database')->query(sprintf('OPTIMIZE TABLE `tbl_entries_data_%d`', $field));
                }
            } catch (Exception $ex) {

            }

            // Save the manifest changes
            Symphony::get('Configuration')->write();
        }

        // 2.2.2
        if (version_compare(self::$existing_version, '2.2.2', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2.2', 'symphony');
        }

        if (Symphony::get('Configuration')->write() === false) {
            throw new Exception('Failed to write configuration file, please check the file permissions.');
        } else {
            return true;
        }
    }
}
