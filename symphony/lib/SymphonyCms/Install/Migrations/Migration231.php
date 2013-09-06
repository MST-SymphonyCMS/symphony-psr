<?php

namespace SymphonyCms\Install\Migrations;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Exceptions\DatabaseException;
use \SymphonyCms\Install\Migration;

/**
 * Migration to 2.3.1
 *
 * @package SymphonyCms
 * @subpackage Install
 */
class Migration231 extends Migration
{
    public static function run($function, $existing_version = null)
    {
        self::$existing_version = $existing_version;

        try {
            $canProceed = self::$function();

            return ($canProceed === false) ? false : true;
        } catch (DatabaseException $e) {
            Symphony::Log()->writeToLog('Could not complete upgrading. MySQL returned: ' . $e->getDatabaseErrorCode() . ': ' . $e->getMessage(), E_ERROR, true);

            return false;
        } catch(Exception $e) {
            Symphony::Log()->writeToLog('Could not complete upgrading because of the following error: ' . $e->getMessage(), E_ERROR, true);

            return false;
        }
    }

    public static function getVersion()
    {
        return '2.3.1';
    }

    public static function getReleaseNotes()
    {
        return 'http://getsymphony.com/download/releases/version/2.3.1/';
    }

    public static function upgrade()
    {
        // 2.3.1dev
        if (version_compare(self::$existing_version, '2.3.1dev', '<=')) {

            // Remove unused setting from the Author field
            $author_table = 'tbl_fieldsauthor';
            if (Symphony::get('Database')->tableContainsField($author_table, 'allowauthor_change')) {
                Symphony::get('Database')->query("ALTER TABLE `$author_table` DROP `allowauthor_change`;");
            }

            // Author Types [#1219]
            if (!Symphony::get('Database')->tableContainsField($author_table, 'author_types')) {
                Symphony::get('Database')->query("ALTER TABLE `$author_table` ADD `author_types` VARCHAR(255) DEFAULT null;");
            }

            // Entries Modification Date [#983]
            if (!Symphony::get('Database')->tableContainsField('tbl_entries', 'modification_date')) {
                Symphony::get('Database')->query("ALTER TABLE `tbl_entries` ADD `modification_date` DATETIME NOT null;");
                Symphony::get('Database')->query("ALTER TABLE `tbl_entries` ADD KEY `modification_date` (`modification_date`)");
                Symphony::get('Database')->query("UPDATE `tbl_entries` SET modification_date = creation_date;");
            }

            if (!Symphony::get('Database')->tableContainsField('tbl_entries', 'modification_date_gmt')) {
                Symphony::get('Database')->query("ALTER TABLE `tbl_entries` ADD `modification_date_gmt` DATETIME NOT null;");
                Symphony::get('Database')->query("ALTER TABLE `tbl_entries` ADD KEY `modification_date_gmt` (`modification_date_gmt`)");
                Symphony::get('Database')->query("UPDATE `tbl_entries` SET modification_date_gmt = creation_date_gmt;");
            }

            // Cleanup #977, remove `entry_order` & `entry_order_direction` from `tbl_sections`
            if (Symphony::get('Database')->tableContainsField('tbl_sections', 'entry_order')) {
                Symphony::get('Database')->query("ALTER TABLE `tbl_sections` DROP `entry_order`;");
            }

            if (Symphony::get('Database')->tableContainsField('tbl_sections', 'entry_order_direction')) {
                Symphony::get('Database')->query("ALTER TABLE `tbl_sections` DROP `entry_order_direction`;");
            }
        }

        if (version_compare(self::$existing_version, '2.3.1RC1', '<=')) {
            // Add Security Rules from 2.2 to .htaccess
            try {
                $htaccess = file_get_contents(DOCROOT . '/.htaccess');

                if ($htaccess !== false && preg_match('/### SECURITY - Protect crucial files/', $htaccess)) {
                    $security = '
        ### SECURITY - Protect crucial files
        RewriteRule ^manifest/(.*)$ - [F]
        RewriteRule ^workspace/(pages|utilities)/(.*)\.xsl$ - [F]
        RewriteRule ^(.*)\.sql$ - [F]
        RewriteRule (^|/)\. - [F]

        ### DO NOT APPLY RULES WHEN REQUESTING "favicon.ico"';

                    $htaccess = str_replace('### SECURITY - Protect crucial files.*### DO NOT APPLY RULES WHEN REQUESTING "favicon.ico"', $security, $htaccess);
                    file_put_contents(DOCROOT . '/.htaccess', $htaccess);
                }
            } catch (Exception $ex) {

            }

            // Increase length of password field to accomodate longer hashes
            Symphony::get('Database')->query("ALTER TABLE `tbl_authors` CHANGE `password` `password` VARCHAR( 150 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT null");
        }

        // Update the version information
        Symphony::get('Configuration')->set('version', self::getVersion(), 'symphony');
        Symphony::get('Configuration')->set('useragent', 'Symphony/' . self::getVersion(), 'general');

        if (Symphony::get('Configuration')->write() === false) {
            throw new Exception('Failed to write configuration file, please check the file permissions.');
        } else {
            return true;
        }
    }
}
