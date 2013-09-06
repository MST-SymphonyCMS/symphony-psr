<?php

namespace SymphonyCms\Install\Migrations;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Exceptions\DatabaseException;
use \SymphonyCms\Install\Migration;

/**
 * Migration to 2.2.0
 *
 * @package SymphonyCms
 * @subpackage Install
 */
class Migration220 extends Migration
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
        return '2.2';
    }

    public static function getReleaseNotes()
    {
        return 'http://getsymphony.com/download/releases/version/2.2/';
    }

    public static function upgrade()
    {
        // 2.2.0dev
        if (version_compare(self::$existing_version, '2.2.0dev', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2dev', 'symphony');

            if (Symphony::get('Database')->tableContainsField('tbl_sections_association', 'cascading_deletion')) {
                Symphony::get('Database')->query(
                    'ALTER TABLE `tbl_sections_association` CHANGE  `cascading_deletion` `hide_association` enum("yes","no") COLLATE utf8_unicode_ci NOT null DEFAULT "no";'
                );

                // Update Select table to include the new association field
                Symphony::get('Database')->query('ALTER TABLE `tbl_fields_select` ADD `show_association` ENUM( "yes", "no" ) COLLATE utf8_unicode_ci NOT null DEFAULT "yes"');
            }

            if (Symphony::get('Database')->tableContainsField('tbl_authors', 'default_section')) {
                // Allow Authors to be set to any area in the backend.
                Symphony::get('Database')->query(
                    'ALTER TABLE `tbl_authors` CHANGE `default_section` `default_area` VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT null;'
                );
            }

            Symphony::get('Configuration')->write();
        }

        // 2.2.0
        if (version_compare(self::$existing_version, '2.2', '<=')) {
            Symphony::get('Configuration')->set('version', '2.2', 'symphony');
            Symphony::get('Configuration')->set('datetime_separator', ' ', 'region');
            Symphony::get('Configuration')->set('strict_error_handling', 'yes', 'symphony');

            // We've added UNIQUE KEY indexes to the Author, Checkbox, Date, Input, Textarea and Upload Fields
            // Time to go through the entry tables and make this change as well.
            $author = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fieldsauthor`");
            $checkbox = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_checkbox`");
            $date = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_date`");
            $input = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_input`");
            $textarea = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_textarea`");
            $upload = Symphony::get('Database')->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_upload`");

            $field_ids = array_merge($author, $checkbox, $date, $input, $textarea, $upload);

            foreach ($field_ids as $id) {
                $table = '`tbl_entries_data_' . $id . '`';

                try {
                    Symphony::get('Database')->query("ALTER TABLE " . $table . " DROP INDEX `entry_id`");
                } catch (Exception $ex) {

                }

                try {
                    Symphony::get('Database')->query("CREATE UNIQUE INDEX `entry_id` ON " . $table . " (`entry_id`)");
                    Symphony::get('Database')->query("OPTIMIZE TABLE " . $table);
                } catch (Exception $ex) {

                }
            }
        }

        if (Symphony::get('Configuration')->write() === false) {
            throw new Exception('Failed to write configuration file, please check the file permissions.');
        } else {
            return true;
        }
    }
}
