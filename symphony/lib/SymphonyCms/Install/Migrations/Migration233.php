<?php

namespace SymphonyCms\Install\Migrations;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Exceptions\DatabaseException;
use \SymphonyCms\Install\Migration;

/**
 * Migration to 2.3.3
 *
 * @package SymphonyCms
 * @subpackage Install
 */
class Migration233 extends Migration
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
        } catch (Exception $e) {
            Symphony::Log()->writeToLog('Could not complete upgrading because of the following error: ' . $e->getMessage(), E_ERROR, true);

            return false;
        }
    }

    public static function getVersion()
    {
        return '2.3.3';
    }

    public static function getReleaseNotes()
    {
        return 'http://getsymphony.com/download/releases/version/2.3.3/';
    }

    public static function upgrade()
    {
        if (version_compare(self::$existing_version, '2.3.3beta1', '<=')) {
            // Update DB for the new author role #1692
            Symphony::get('Database')->query(
                sprintf(
                    "ALTER TABLE `tbl_authors` CHANGE `user_type` `user_type` enum('author', 'manager', 'developer') DEFAULT 'author'",
                    $field
                )
            );

            // Remove directory from the upload fields, #1719
            $upload_tables = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_upload`");

            if (is_array($upload_tables) && !empty($upload_tables)) {
                foreach ($upload_tables as $field) {
                    Symphony::get('Database')->query(
                        sprintf(
                            "UPDATE tbl_entries_data_%d SET file = substring_index(file, '/', -1)",
                            $field
                        )
                    );
                }
            }
        }

        if (version_compare(self::$existing_version, '2.3.3beta2', '<=')) {
            // Update rows for associations
            if (!Symphony::get('Configuration')->get('association_maximum_rows', 'symphony')) {
                Symphony::get('Configuration')->set('association_maximum_rows', '5', 'symphony');
            }
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

    public static function preUpdateNotes()
    {
        return array(
            tr("On update, all files paths will be removed from the core Upload field entry tables. If you are using an Upload field extension, ensure that the extension is compatible with this release before continuing.")
        );
    }
}
