<?php

namespace \SymphonyCms\Toolkit;

use \SymphonyCms\Symphony;
use \SymphonyCms\Toolkit\Author;

/**
 * The `AuthorManager` class is responsible for managing all Author objects
 * in Symphony. Unlike other Manager objects, Authors are stored in the
 * database in `tblauthors` and not on the file system. CRUD methods are
 * implemented to allow Authors to be created (add), read (fetch), updated
 * (edit) and deleted (delete).
 */
class AuthorManager
{
    /**
     * An array of all the objects that the Manager is responsible for.
     * Defaults to an empty array.
     * @var array
     */
    protected static $_pool = array();

    /**
     * Given an associative array of fields, insert them into the database
     * returning the resulting Author ID if successful, or false if there
     * was an error
     *
     * @param array $fields
     *  Associative array of field names => values for the Author object
     * @return integer|boolean
     *  Returns an Author ID of the created Author on success, false otherwise.
     */
    public static function add(array $fields)
    {
        if (!Symphony::Database()->insert($fields, 'tblauthors')) {
            return false;
        }

        $author_id = Symphony::Database()->getInsertID();

        return $author_id;
    }

    /**
     * Given an Author ID and associative array of fields, update an existing Author
     * row in the `tblauthors` database table. Returns boolean for success/failure
     *
     * @param integer $id
     *  The ID of the Author that should be updated
     * @param array $fields
     *  Associative array of field names => values for the Author object
     *  This array does need to contain every value for the author object, it
     *  can just be the changed values.
     * @return boolean
     */
    public static function edit($id, array $fields)
    {
        return Symphony::Database()->update(
            $fields,
            'tblauthors',
            sprintf(
                " `id` = %d",
                $id
            )
        );
    }

    /**
     * Given an Author ID, delete an Author from Symphony.
     *
     * @param integer $id
     *  The ID of the Author that should be deleted
     * @return boolean
     */
    public static function delete($id)
    {
        return Symphony::Database()->delete(
            'tblauthors',
            sprintf(
                " `id` = %d",
                $id
            )
        );
    }

    /**
     * The fetch method returns all Authors from Symphony with the option to sort
     * or limit the output. This method returns an array of Author objects.
     *
     * @param string $sortby
     *  The field to sort the authors by, defaults to 'id'
     * @param string $sortdirection
     *  Available values of ASC (Ascending) or DESC (Descending), which refer to the
     *  sort order for the query. Defaults to ASC (Ascending)
     * @param integer $limit
     *  The number of rows to return
     * @param integer $start
     *  The offset start point for limiting, maps to the LIMIT {x}, {y} MySQL functionality
     * @param string $where
     *  Any custom WHERE clauses. The `tblauthors` alias is `a`
     * @param string $joins
     *  Any custom JOIN's
     * @return array
     *  An array of Author objects. If no Authors are found, an empty array is returned.
     */
    public static function fetch($sortby = 'id', $sortdirection = 'ASC', $limit = null, $start = null, $where = null, $joins = null)
    {
        $sortby = is_null($sortby) ? 'id' : Symphony::Database()->cleanValue($sortby);
        $sortdirection = $sortdirection === 'ASC' ? 'ASC' : 'DESC';

        $records = Symphony::Database()->fetch(
            sprintf(
                "SELECT a.*
                FROM `tblauthors` AS `a`
                %s
                WHERE %s
                ORDER BY %s %s
                %s %s",
                $joins,
                ($where ? $where : 1),
                'a.'. $sortby,
                $sortdirection,
                ($limit ? "LIMIT " . $limit : ''),
                ($start && $limit ? ', ' . $start : '')
            )
        );

        if (!is_array($records) || empty($records)) {
            return array();
        }

        $authors = array();

        foreach ($records as $row) {
            $author = new Author;

            foreach ($row as $field => $val) {
                $author->set($field, $val);
            }

            self::$_pool[$author->get('id')] = $author;
            $authors[] = $author;
        }

        return $authors;
    }

    /**
     * Returns Author's that match the provided ID's with the option to
     * sort or limit the output. This function will search the
     * `AuthorManager::$_pool` for Authors first before querying `tblauthors`
     *
     * @param integer|array $id
     *  A single ID or an array of ID's
     * @return mixed
     *  If `$id` is an integer, the result will be an Author object,
     *  otherwise an array of Author objects will be returned. If no
     *  Authors are found, or no `$id` is given, `null` is returned.
     */
    public static function fetchByID($id)
    {
        $return_single = false;

        if (is_null($id)) {
            return null;
        }

        if (!is_array($id)) {
            $return_single = true;
            $id = array((int)$id);
        }

        if (empty($id)) {
            return null;
        }

        $authors = array();
        $pooledauthors = array();

        // Get all the Author ID's that are already in `self::$_pool`
        $pooledauthors = array_intersect($id, array_keys(self::$_pool));

        foreach ($pooledauthors as $poolauthor) {
            $authors[] = self::$_pool[$poolauthor];
        }

        // Get all the Author ID's that are not already stored in `self::$_pool`
        $id = array_diff($id, array_keys(self::$_pool));
        $id = array_filter($id);

        if (empty($id)) {
            return ($return_single ? $authors[0] : $authors);
        }

        $records = Symphony::Database()->fetch(
            sprintf(
                "SELECT *
                FROM `tblauthors`
                WHERE `id` IN (%s)",
                implode(",", $id)
            )
        );

        if (!is_array($records) || empty($records)) {
            return ($return_single ? $authors[0] : $authors);
        }

        foreach ($records as $row) {
            $author = new Author;

            foreach ($row as $field => $val) {
                $author->set($field, $val);
            }

            self::$_pool[$author->get('id')] = $author;
            $authors[] = $author;
        }

        return ($return_single ? $authors[0] : $authors);
    }

    /**
     * Returns an Author by Username. This function will search the
     * `AuthorManager::$_pool` for Authors first before querying `tblauthors`
     *
     * @param string $username
     *  The Author's username
     * @return Author|null
     *  If an Author is found, an Author object is returned, otherwise null.
     */
    public static function fetchByUsername($username)
    {
        if (!isset(self::$_pool[$username])) {
            $records = Symphony::Database()->fetchRow(
                0,
                sprintf(
                    "SELECT *
                    FROM `tblauthors`
                    WHERE `username` = '%s'
                    LIMIT 1",
                    Symphony::Database()->cleanValue($username)
                )
            );

            if (!is_array($records) || empty($records)) {
                return array();
            }

            $author = new Author;

            foreach ($records as $field => $val) {
                $author->set($field, $val);
            }

            self::$_pool[$username] = $author;
        }

        return self::$_pool[$username];
    }

    /**
     * This function will allow an Author to sign into Symphony by using their
     * authentication token as well as username/password.
     *
     * @param integer $author_id
     *  The Author ID to allow to use their authentication token.
     * @return boolean
     */
    public static function activateAuthToken($author_id)
    {
        if (!is_int($author_id)) {
            return false;
        }

        return Symphony::Database()->query(
            sprintf(
                "UPDATE `tblauthors`
                SET `auth_token_active` = 'yes'
                WHERE `id` = %d",
                $author_id
            )
        );
    }

    /**
     * This function will remove the ability for an Author to sign into Symphony
     * by using their authentication token
     *
     * @param integer $author_id
     *  The Author ID to allow to use their authentication token.
     * @return boolean
     */
    public static function deactivateAuthToken($author_id)
    {
        if (!is_int($author_id)) {
            return false;
        }

        return Symphony::Database()->query(
            sprintf(
                "UPDATE `tblauthors`
                SET `auth_token_active` = 'no'
                WHERE `id` = %d",
                $author_id
            )
        );
    }

}
