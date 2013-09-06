<?php

namespace SymphonyCms\Install\Migrations;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Exceptions\DatabaseException;
use \SymphonyCms\Install\Migration;

/**
 * Migration to 2.2.1
 *
 * @package SymphonyCms
 * @subpackage Install
 */
class Migration221 extends Migration
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
        return '2.2.1';
    }

    public static function getReleaseNotes()
    {
        return 'http://getsymphony.com/download/releases/version/2.2.1/';
    }

    public static function upgrade()
    {
        // 2.2.1 Beta 1
        if (version_compare(self::$existing_version, '2.2.1 Beta 1', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2.1 Beta 1', 'symphony');

            try {
                Symphony::get('Database')->query('CREATE INDEX `session_expires` ON `tbl_sessions` (`session_expires`)');
                Symphony::get('Database')->query('OPTIMIZE TABLE `tbl_sessions`');
            } catch (Exception $ex) {

            }

            Symphony::get('Configuration')->write();
        }

        // 2.2.1 Beta 2
        if (version_compare(self::$existing_version, '2.2.1 Beta 2', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2.1 Beta 2', 'symphony');

            // Add Security Rules from 2.2 to .htaccess
            try {
                $htaccess = file_get_contents(DOCROOT . '/.htaccess');

                if ($htaccess !== false && !preg_match('/### SECURITY - Protect crucial files/', $htaccess)) {
                    $security = '
        ### SECURITY - Protect crucial files
        RewriteRule ^manifest/(.*)$ - [F]
        RewriteRule ^workspace/(pages|utilities)/(.*)\.xsl$ - [F]
        RewriteRule ^(.*)\.sql$ - [F]
        RewriteRule (^|/)\. - [F]

        ### DO NOT APPLY RULES WHEN REQUESTING "favicon.ico"';

                    $htaccess = str_replace('### DO NOT APPLY RULES WHEN REQUESTING "favicon.ico"', $security, $htaccess);
                    file_put_contents(DOCROOT . '/.htaccess', $htaccess);
                }
            } catch (Exception $ex) {

            }

            // Add correct index to the `tbl_cache`
            try {
                Symphony::get('Database')->query('ALTER TABLE `tbl_cache` DROP INDEX `creation`');
                Symphony::get('Database')->query('CREATE INDEX `expiry` ON `tbl_cache` (`expiry`)');
                Symphony::get('Database')->query('OPTIMIZE TABLE `tbl_cache`');
            } catch (Exception $ex) {

            }

            // Remove Hide Association field from Select Data tables
            $select_tables = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_select`");

            if (is_array($select_tables) && !empty($select_tables)) {
                foreach ($select_tables as $field) {
                    if (Symphony::get('Database')->tableContainsField('tbl_entries_data_' . $field, 'show_association')) {
                        Symphony::get('Database')->query(
                            sprintf(
                                "ALTER TABLE `tbl_entries_data_%d` DROP `show_association`",
                                $field
                            )
                        );
                    }
                }
            }

            // Update Select table to include the sorting option
            if (!Symphony::get('Database')->tableContainsField('tbl_fields_select', 'sort_options')) {
                Symphony::get('Database')->query('ALTER TABLE `tbl_fields_select` ADD `sort_options` ENUM( "yes", "no" ) COLLATE utf8_unicode_ci NOT null DEFAULT "no"');
            }

            // Remove the 'driver' from the Config
            Symphony::get('Configuration')->remove('driver', 'database');
            Symphony::get('Configuration')->write();

            // Remove the NOT null from the Author tables
            try {
                $author = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fieldsauthor`");

                foreach ($author as $id) {
                    $table = '`tbl_entries_data_' . $id . '`';

                    Symphony::get('Database')->query(
                        'ALTER TABLE ' . $table . ' CHANGE `author_id` `author_id` int(11) unsigned null'
                    );
                }
            } catch (Exception $ex) {

            }

            Symphony::get('Configuration')->write();
        }

        // 2.2.1
        if (version_compare(self::$existing_version, '2.2.1', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2.1', 'symphony');
        }

        if (Symphony::get('Configuration')->write() === false) {
            throw new Exception('Failed to write configuration file, please check the file permissions.');
        } else {
            return true;
        }
    }

    public static function postUpdateNotes()
    {
        return array(
            tr('Version %s introduces some improvements and fixes to Static XML Datasources. If you have any Static XML Datasources in your installation, please be sure to re-save them through the Data Source Editor to prevent unexpected results.', array('<code>2.2.1</code>'))
        );
    }
}
