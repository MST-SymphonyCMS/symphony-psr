<?php

namespace SymphonyCms\Pages\Content;

use \SymphonyCms\Symphony;
use \SymphonyCms\Symphony\DateTimeObj;
use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Pages\AdministrationPage;
use \SymphonyCms\Toolkit\Author;
use \SymphonyCms\Toolkit\AuthorManager;
use \SymphonyCms\Toolkit\Cryptography;
use \SymphonyCms\Toolkit\Lang;
use \SymphonyCms\Toolkit\Page;
use \SymphonyCms\Toolkit\SectionManager;
use \SymphonyCms\Toolkit\Sortable;
use \SymphonyCms\Toolkit\Widget;
use \SymphonyCms\Toolkit\XMLElement;
use \SymphonyCms\Utilities\General;

/**
 * Controller page for all Symphony Author related activity
 * including making new Authors, editing Authors or deleting
 * Authors from Symphony
 */
class SystemAuthorsPage extends AdministrationPage
{
    public $author;
    public $_errors = array();

    public function sort(&$sort, &$order, $params)
    {
        if (is_null($sort) || $sort == 'name') {
            $sort = 'name';
            return AuthorManager::fetch("first_name $order,  last_name", $order);
        }

        return AuthorManager::fetch($sort, $order);
    }

    public function viewIndex()
    {
        $this->setPageType('table');
        $this->setTitle(tr('%1$s &ndash; %2$s', array(tr('Authors'), tr('Symphony'))));

        if (Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) {
            $this->appendSubheading(tr('Authors'), Widget::Anchor(tr('Create New'), Administration::instance()->getCurrentPageURL().'new/', tr('Create a new author'), 'create button', null, array('accesskey' => 'c')));
        } else {
            $this->appendSubheading(tr('Authors'));
        }

        Sortable::initialize($this, $authors, $sort, $order);

        $columns = array(
            array(
                'label' => tr('Name'),
                'sortable' => true,
                'handle' => 'name'
            ),
            array(
                'label' => tr('Email Address'),
                'sortable' => true,
                'handle' => 'email'
            ),
            array(
                'label' => tr('Last Seen'),
                'sortable' => true,
                'handle' => 'last_seen'
            )
        );

        if (Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) {
            $columns = array_merge(
                $columns,
                array(
                    array(
                        'label' => tr('User Type'),
                        'sortable' => true,
                        'handle' => 'user_type'
                    ),
                    array(
                        'label' => tr('Language'),
                        'sortable' => true,
                        'handle' => 'language'
                    )
                )
            );
        }

        $aTableHead = Sortable::buildTableHeaders(
            $columns,
            $sort,
            $order,
            (isset($_REQUEST['filter']) ? '&amp;filter=' . $_REQUEST['filter'] : '')
        );

        $aTableBody = array();

        if (!is_array($authors) || empty($authors)) {
            $aTableBody = array(
                Widget::TableRow(array(Widget::TableData(tr('None found.'), 'inactive', null, count($aTableHead))), 'odd')
            );
        } else {
            foreach ($authors as $a) {
                if (Administration::instance()->Author->isManager() && $a->isDeveloper()) {
                    continue;
                }
                // Setup each cell
                if ((Administration::instance()->Author->isDeveloper() || (Administration::instance()->Author->isManager() && !$a->isDeveloper()))
                    || Administration::instance()->Author->get('id') == $a->get('id')
                ) {
                    $td1 = Widget::TableData(
                        Widget::Anchor($a->getFullName(), Administration::instance()->getCurrentPageURL() . 'edit/' . $a->get('id') . '/', $a->get('username'), 'author')
                    );
                } else {
                    $td1 = Widget::TableData($a->getFullName(), 'inactive');
                }

                $td2 = Widget::TableData(Widget::Anchor($a->get('email'), 'mailto:'.$a->get('email'), tr('Email this author')));

                if (!is_null($a->get('last_seen'))) {
                    $td3 = Widget::TableData(
                        DateTimeObj::format($a->get('last_seen'), __SYM_DATETIME_FORMAT__)
                    );
                } else {
                    $td3 = Widget::TableData(tr('Unknown'), 'inactive');
                }

                if ($a->isDeveloper()) {
                    $type = 'Developer';
                } elseif ($a->isManager()) {
                    $type = 'Manager';
                } else {
                    $type = 'Author';
                }
                $td4 = Widget::TableData(tr($type));

                $languages = Lang::getAvailableLanguages();

                $td5 = Widget::TableData($a->get("language") == null ? tr("System Default") : $languages[$a->get("language")]);

                if (Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) {
                    if ($a->get('id') != Administration::instance()->Author->get('id')) {
                        $td3->appendChild(Widget::Input('items['.$a->get('id').']', null, 'checkbox'));
                    }
                }

                // Add a row to the body array, assigning each cell to the row
                if (Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) {
                    $aTableBody[] = Widget::TableRow(array($td1, $td2, $td3, $td4, $td5));
                } else {
                    $aTableBody[] = Widget::TableRow(array($td1, $td2, $td3));
                }
            }
        }

        $table = Widget::Table(
            Widget::TableHead($aTableHead),
            null,
            Widget::TableBody($aTableBody),
            'selectable'
        );

        $this->Form->appendChild($table);

        if (Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) {
            $tableActions = new XMLElement('div');
            $tableActions->setAttribute('class', 'actions');

            $options = array(
                array(null, false, tr('With Selected...')),
                array('delete', false, tr('Delete'), 'confirm', null, array(
                    'data-message' => tr('Are you sure you want to delete the selected authors?')
                ))
            );

            /**
             * Allows an extension to modify the existing options for this page's
             * With Selected menu. If the `$options` parameter is an empty array,
             * the 'With Selected' menu will not be rendered.
             *
             * @delegate AddCustomActions
             * @since Symphony 2.3.2
             * @param string $context
             * '/system/authors/'
             * @param array $options
             *  An array of arrays, where each child array represents an option
             *  in the With Selected menu. Options should follow the same format
             *  expected by `Widget::selectBuildOption`. Passed by reference.
             */
            Symphony::ExtensionManager()->notifyMembers(
                'AddCustomActions',
                '/system/authors/',
                array(
                    'options' => &$options
                )
            );

            if (!empty($options)) {
                $tableActions->appendChild(Widget::Apply($options));
                $this->Form->appendChild($tableActions);
            }
        }
    }

    public function actionIndex()
    {
        $checked = (is_array($_POST['items'])) ? array_keys($_POST['items']) : null;

        if (is_array($checked) && !empty($checked)) {
            /**
             * Extensions can listen for any custom actions that were added
             * through `AddCustomPreferenceFieldsets` or `AddCustomActions`
             * delegates.
             *
             * @delegate CustomActions
             * @since Symphony 2.3.2
             * @param string $context
             *  '/system/authors/'
             * @param array $checked
             *  An array of the selected rows. The value is usually the ID of the
             *  the associated object.
             */
            Symphony::ExtensionManager()->notifyMembers(
                'CustomActions',
                '/system/authors/',
                array(
                    'checked' => $checked
                )
            );

            if ($_POST['with-selected'] == 'delete') {
                /**
                * Prior to deleting an author, provided with an array of Author ID's.
                *
                * @delegate AuthorPreDelete
                * @since Symphony 2.2
                * @param string $context
                * '/system/authors/'
                * @param array $author_ids
                *  An array of Author ID that are about to be removed
                */
                Symphony::ExtensionManager()->notifyMembers('AuthorPreDelete', '/system/authors/', array('author_ids' => $checked));

                foreach ($checked as $author_id) {
                    $a = AuthorManager::fetchByID($author_id);
                    if (is_object($a) && $a->get('id') != Administration::instance()->Author->get('id')) {
                        AuthorManager::delete($author_id);
                    }
                }

                redirect(SYMPHONY_URL . '/system/authors/');
            }
        }
    }

    // Both the Edit and New pages need the same form
    public function viewNew()
    {
        $this->form();
    }

    public function viewEdit()
    {
        $this->form();
    }

    public function form()
    {
        // Handle unknown context
        if (!in_array($this->_context[0], array('new', 'edit'))) {
            Administration::instance()->errorPageNotFound();
        }

        if ($this->_context[0] == 'new' && !Administration::instance()->Author->isDeveloper() && !Administration::instance()->Author->isManager()) {
            Administration::instance()->throwCustomError(
                tr('You are not authorised to access this page.'),
                tr('Access Denied'),
                Page::HTTP_STATUS_UNAUTHORIZED
            );
        }

        if (isset($this->_context[2])) {
            switch ($this->_context[2]) {
                case 'saved':
                    $this->pageAlert(
                        tr('Author updated at %s.', array(DateTimeObj::getTimeAgo()))
                        . ' <a href="' . SYMPHONY_URL . '/system/authors/new/" accesskey="c">'
                        . tr('Create another?')
                        . '</a> <a href="' . SYMPHONY_URL . '/system/authors/" accesskey="a">'
                        . tr('View all Authors')
                        . '</a>',
                        Alert::SUCCESS
                    );
                    break;
                case 'created':
                    $this->pageAlert(
                        tr('Author created at %s.', array(DateTimeObj::getTimeAgo()))
                        . ' <a href="' . SYMPHONY_URL . '/system/authors/new/" accesskey="c">'
                        . tr('Create another?')
                        . '</a> <a href="' . SYMPHONY_URL . '/system/authors/" accesskey="a">'
                        . tr('View all Authors')
                        . '</a>',
                        Alert::SUCCESS
                    );
                    break;
            }
        }

        $this->setPageType('form');

        $isOwner = false;

        if (isset($_POST['fields'])) {
            $author = $this->author;
        } elseif ($this->_context[0] == 'edit') {
            if (!$author_id = (int)$this->_context[1]) {
                redirect(SYMPHONY_URL . '/system/authors/');
            }

            if (!$author = AuthorManager::fetchByID($author_id)) {
                Administration::instance()->throwCustomError(
                    tr('The author profile you requested does not exist.'),
                    tr('Author not found'),
                    Page::HTTP_STATUS_NOT_FOUND
                );
            }
        } else {
            $author = new Author;
        }

        if ($this->_context[0] == 'edit' && $author->get('id') == Administration::instance()->Author->get('id')) {
            $isOwner = true;
        }

        if ($this->_context[0] == 'edit' && !$isOwner && !Administration::instance()->Author->isDeveloper() && !Administration::instance()->Author->isManager()) {
            Administration::instance()->throwCustomError(
                tr('You are not authorised to edit other authors.'),
                tr('Access Denied'),
                Page::HTTP_STATUS_FORBIDDEN
            );
        }

        $this->setTitle(tr(($this->_context[0] == 'new' ? '%2$s &ndash; %3$s' : '%1$s &ndash; %2$s &ndash; %3$s'), array($author->getFullName(), tr('Authors'), tr('Symphony'))));
        $this->appendSubheading(($this->_context[0] == 'new' ? tr('Untitled') : $author->getFullName()));
        $this->insertBreadcrumbs(
            array(
                Widget::Anchor(tr('Authors'), SYMPHONY_URL . '/system/authors/'),
            )
        );

        // Essentials
        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');
        $group->appendChild(new XMLElement('legend', tr('Essentials')));

        $div = new XMLElement('div');
        $div->setAttribute('class', 'two columns');

        $label = Widget::Label(tr('First Name'), null, 'column');
        $label->appendChild(Widget::Input('fields[first_name]', $author->get('first_name')));
        $div->appendChild((isset($this->_errors['first_name']) ? Widget::Error($label, $this->_errors['first_name']) : $label));

        $label = Widget::Label(tr('Last Name'), null, 'column');
        $label->appendChild(Widget::Input('fields[last_name]', $author->get('last_name')));
        $div->appendChild((isset($this->_errors['last_name']) ? Widget::Error($label, $this->_errors['last_name']) : $label));

        $group->appendChild($div);

        $label = Widget::Label(tr('Email Address'));
        $label->appendChild(Widget::Input('fields[email]', $author->get('email')));
        $group->appendChild((isset($this->_errors['email']) ? Widget::Error($label, $this->_errors['email']) : $label));

        $this->Form->appendChild($group);

        // Login Details
        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');
        $group->appendChild(new XMLElement('legend', tr('Login Details')));

        $div = new XMLElement('div');

        $label = Widget::Label(tr('Username'));
        $label->appendChild(Widget::Input('fields[username]', $author->get('username')));
        $div->appendChild((isset($this->_errors['username']) ? Widget::Error($label, $this->_errors['username']) : $label));

        // Only developers can change the user type. Primary account should NOT be able to change this
        if ((Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) && !$author->isPrimaryAccount()) {
            // Create columns
            $div->setAttribute('class', 'two columns');
            $label->setAttribute('class', 'column');

            // User type
            $label = Widget::Label(tr('User Type'), null, 'column');

            $options = array(
                array('author', false, tr('Author')),
                array('manager', $author->isManager(), tr('Manager'))
            );

            if (Administration::instance()->Author->isDeveloper()) {
                $options[] = array('developer', $author->isDeveloper(), tr('Developer'));
            }

            $label->appendChild(Widget::Select('fields[user_type]', $options));
            $div->appendChild($label);
        }

        $group->appendChild($div);

        // Password
        $fieldset = new XMLElement('fieldset', null, array('class' => 'two columns', 'id' => 'password'));
        $legend = new XMLElement('legend', tr('Password'));
        $help = new XMLElement('i', tr('Leave password fields blank to keep the current password'));
        $fieldset->appendChild($legend);
        $fieldset->appendChild($help);

        // Password reset
        if ($this->_context[0] == 'edit' && (!Administration::instance()->Author->isDeveloper() || !Administration::instance()->Author->isManager() || $isOwner === true)) {
            $fieldset->setAttribute('class', 'three columns');

            $label = Widget::Label(null, null, 'column');
            $label->appendChild(Widget::Input('fields[old-password]', null, 'password', array('placeholder' => tr('Old Password'))));
            $fieldset->appendChild((isset($this->_errors['old-password']) ? Widget::Error($label, $this->_errors['old-password']) : $label));
        }

        // New password
        $callback = Administration::instance()->getPageCallback();
        $placeholder = ($callback['context'][0] == 'edit' ? tr('New Password') : tr('Password'));
        $label = Widget::Label(null, null, 'column');
        $label->appendChild(Widget::Input('fields[password]', null, 'password', array('placeholder' => $placeholder)));
        $fieldset->appendChild((isset($this->_errors['password']) ? Widget::Error($label, $this->_errors['password']) : $label));

        // Confirm password
        $label = Widget::Label(null, null, 'column');
        $label->appendChild(Widget::Input('fields[password-confirmation]', null, 'password', array('placeholder' => tr('Confirm Password'))));
        $fieldset->appendChild((isset($this->_errors['password-confirmation']) ? Widget::Error($label, $this->_errors['password']) : $label));

        $group->appendChild($fieldset);

        // Auth token
        if (Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) {
            $label = Widget::Label();
            $group->appendChild(Widget::Input('fields[auth_token_active]', 'no', 'hidden'));
            $input = Widget::Input('fields[auth_token_active]', 'yes', 'checkbox');

            if ($author->isTokenActive()) {
                $input->setAttribute('checked', 'checked');
            }

            $temp = SYMPHONY_URL . '/login/' . $author->createAuthToken() . '/';
            $label->setValue(tr('%s Allow remote login via', array($input->generate())) . ' <a href="' . $temp . '">' . $temp . '</a>');
            $group->appendChild($label);
        }

        $label = Widget::Label(tr('Default Area'));

        $sections = SectionManager::fetch(null, 'ASC', 'sortorder');

        $options = array();

        // If the Author is the Developer, allow them to set the Default Area to
        // be the Sections Index.
        if ($author->isDeveloper() || $author->isManager()) {
            $options[] = array('/blueprints/sections/', $author->get('default_area') == '/blueprints/sections/', tr('Sections Index'));
        }

        if (is_array($sections) && !empty($sections)) {
            foreach ($sections as $s) {
                $options[] = array($s->get('id'), $author->get('default_area') == $s->get('id'), $s->get('name'));
            }
        }

        /**
        * Allows injection or manipulation of the Default Area dropdown for an Author.
        * Take care with adding in options that are only valid for Developers, as if a
        * normal Author is set to that option, they will be redirected to their own
        * Author record.
        *
        *
        * @delegate AddDefaultAuthorAreas
        * @since Symphony 2.2
        * @param string $context
        * '/system/authors/'
        * @param array $options
        * An associative array of options, suitable for use for the Widget::Select
        * function. By default this will be an array of the Sections in the current
        * installation. New options should be the path to the page after the `SYMPHONY_URL`
        * constant.
        * @param string $default_area
        * The current `default_area` for this Author.
        */
        Symphony::ExtensionManager()->notifyMembers(
            'AddDefaultAuthorAreas',
            '/system/authors/',
            array(
                'options' => &$options,
                'default_area' => $author->get('default_area')
            )
        );

        $label->appendChild(Widget::Select('fields[default_area]', $options));
        $group->appendChild($label);

        $this->Form->appendChild($group);

        // Custom Language Selection
        $languages = Lang::getAvailableLanguages();

        if (count($languages) > 1) {
            // Get language names
            asort($languages);

            $group = new XMLElement('fieldset');
            $group->setAttribute('class', 'settings');
            $group->appendChild(new XMLElement('legend', tr('Custom Preferences')));

            $label = Widget::Label(tr('Language'));

            $options = array(
                array(null, is_null($author->get('language')), tr('System Default'))
            );

            foreach ($languages as $code => $name) {
                $options[] = array($code, $code == $author->get('language'), $name);
            }
            $select = Widget::Select('fields[language]', $options);
            $label->appendChild($select);
            $group->appendChild($label);

            $this->Form->appendChild($group);
        }

        $div = new XMLElement('div');
        $div->setAttribute('class', 'actions');

        $div->appendChild(Widget::Input('action[save]', ($this->_context[0] == 'edit' ? tr('Save Changes') : tr('Create Author')), 'submit', array('accesskey' => 's')));

        if ($this->_context[0] == 'edit' && !$isOwner && !$author->isPrimaryAccount()) {
            $button = new XMLElement('button', tr('Delete'));
            $button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => tr('Delete this author'), 'type' => 'submit', 'accesskey' => 'd', 'data-message' => tr('Are you sure you want to delete this author?')));
            $div->appendChild($button);
        }

        $this->Form->appendChild($div);

        /**
        * Allows the injection of custom form fields given the current `$this->Form`
        * object. Please note that this custom data should be saved in own extension
        * tables and that modifying `tblauthors` to house your data is highly discouraged.
        *
        * @delegate AddElementstoAuthorForm
        * @since Symphony 2.2
        * @param string $context
        * '/system/authors/'
        * @param XMLElement $form
        * The contents of `$this->Form` after all the default form elements have been appended.
        * @param Author $author
        * The current Author object that is being edited
        */
        Symphony::ExtensionManager()->notifyMembers(
            'AddElementstoAuthorForm',
            '/system/authors/',
            array(
                'form' => &$this->Form,
                'author' => $author
            )
        );
    }

    public function actionNew()
    {
        if (@array_key_exists('save', $_POST['action']) || @array_key_exists('done', $_POST['action'])) {
            $fields = $_POST['fields'];

            $this->author = new Author;
            $this->author->set('user_type', $fields['user_type']);
            $this->author->set('primary', 'no');
            $this->author->set('email', $fields['email']);
            $this->author->set('username', $fields['username']);
            $this->author->set('first_name', General::sanitize($fields['first_name']));
            $this->author->set('last_name', General::sanitize($fields['last_name']));
            $this->author->set('last_seen', null);
            $this->author->set('password', (trim($fields['password']) == '' ? '' : Cryptography::hash(Symphony::Database()->cleanValue($fields['password']))));
            $this->author->set('default_area', $fields['default_area']);
            $this->author->set('auth_token_active', ($fields['auth_token_active'] ? $fields['auth_token_active'] : 'no'));
            $this->author->set('language', isset($fields['language']) ? $fields['language'] : null);

            if ($this->author->validate($this->_errors)) {
                if ($fields['password'] != $fields['password-confirmation']) {
                    $this->_errors['password'] = $this->_errors['password-confirmation'] = tr('Passwords did not match');
                } elseif ($author_id = $this->author->commit()) {
                    /**
                     * Creation of a new Author. The Author object is provided as read
                     * only through this delegate.
                     *
                     * @delegate AuthorPostCreate
                     * @since Symphony 2.2
                     * @param string $context
                     * '/system/authors/'
                     * @param Author $author
                     *  The Author object that has just been created
                     */
                    Symphony::ExtensionManager()->notifyMembers(
                        'AuthorPostCreate',
                        '/system/authors/',
                        array('author' => $this->author)
                    );

                    redirect(SYMPHONY_URL . "/system/authors/edit/$author_id/created/");
                }
            }

            if (is_array($this->_errors) && !empty($this->_errors)) {
                $this->pageAlert(tr('There were some problems while attempting to save. Please check below for problem fields.'), Alert::ERROR);
            } else {
                $this->pageAlert(
                    tr('Unknown errors occurred while attempting to save.')
                    . '<a href="' . SYMPHONY_URL . '/system/log/">'
                    . tr('Check your activity log')
                    . '</a>.',
                    Alert::ERROR
                );
            }
        }
    }

    public function actionEdit()
    {
        if (!$author_id = (int)$this->_context[1]) {
            redirect(SYMPHONY_URL . '/system/authors/');
        }

        $isOwner = ($author_id == Administration::instance()->Author->get('id'));

        if (@array_key_exists('save', $_POST['action']) || @array_key_exists('done', $_POST['action'])) {
            $fields = $_POST['fields'];
            $this->author = AuthorManager::fetchByID($author_id);
            $authenticated = false;

            if ($fields['email'] != $this->author->get('email')) {
                $changing_email = true;
            }

            // Check the old password was correct
            if (isset($fields['old-password']) && strlen(trim($fields['old-password'])) > 0 && Cryptography::compare(Symphony::Database()->cleanValue(trim($fields['old-password'])), $this->author->get('password'))) {
                $authenticated = true;
            } elseif (Administration::instance()->Author->isDeveloper()) {
                // Developers don't need to specify the old password, unless it's their own account
                $authenticated = true;
            }

            $this->author->set('id', $author_id);

            if ($this->author->isPrimaryAccount() || ($isOwner && Administration::instance()->Author->isDeveloper())) {
                $this->author->set('user_type', 'developer'); // Primary accounts are always developer, Developers can't lower their level
            } elseif ((Administration::instance()->Author->isDeveloper() || Administration::instance()->Author->isManager()) && isset($fields['user_type'])) {
                $this->author->set('user_type', $fields['user_type']); // Only developer can change user type
            }

            $this->author->set('email', $fields['email']);
            $this->author->set('username', $fields['username']);
            $this->author->set('first_name', General::sanitize($fields['first_name']));
            $this->author->set('last_name', General::sanitize($fields['last_name']));
            $this->author->set('language', isset($fields['language']) ? $fields['language'] : null);

            if (trim($fields['password']) != '') {
                $this->author->set('password', Cryptography::hash(Symphony::Database()->cleanValue($fields['password'])));
                $changing_password = true;
            }

            // Don't allow authors to set the Section Index as a default area
            // If they had it previously set, just save `null` which will redirect
            // the Author (when logging in) to their own Author record
            if ($this->author->get('user_type') == 'author'
                && $fields['default_area'] == '/blueprints/sections/'
            ) {
                $this->author->set('default_area', null);
            } else {
                $this->author->set('default_area', $fields['default_area']);
            }

            $this->author->set('auth_token_active', ($fields['auth_token_active'] ? $fields['auth_token_active'] : 'no'));

            if ($this->author->validate($this->_errors)) {
                if (!$authenticated && ($changing_password || $changing_email)) {
                    if ($changing_password) {
                        $this->_errors['old-password'] = tr('Wrong password. Enter old password to change it.');
                    } elseif ($changing_email) {
                        $this->_errors['old-password'] = tr('Wrong password. Enter old one to change email address.');
                    }
                } elseif (($fields['password'] != '' || $fields['password-confirmation'] != '') && $fields['password'] != $fields['password-confirmation']) {
                    $this->_errors['password'] = $this->_errors['password-confirmation'] = tr('Passwords did not match');
                } elseif ($this->author->commit()) {
                    Symphony::Database()->delete('tbl_forgotpass', " `expiry` < '".DateTimeObj::getGMT('c')."' OR `author_id` = '".$author_id."' ");

                    if ($isOwner) {
                        Administration::instance()->login($this->author->get('username'), $this->author->get('password'), true);
                    }

                    /**
                     * After editing an author, provided with the Author object
                     *
                     * @delegate AuthorPostEdit
                     * @since Symphony 2.2
                     * @param string $context
                     * '/system/authors/'
                     * @param Author $author
                     * An Author object
                     */
                    Symphony::ExtensionManager()->notifyMembers('AuthorPostEdit', '/system/authors/', array('author' => $this->author));

                    redirect(SYMPHONY_URL . '/system/authors/edit/' . $author_id . '/saved/');
                } else {
                    $this->pageAlert(
                        tr('Unknown errors occurred while attempting to save.')
                        . '<a href="' . SYMPHONY_URL . '/system/log/">'
                        . tr('Check your activity log')
                        . '</a>.',
                        Alert::ERROR
                    );
                }
            } elseif (is_array($this->_errors) && !empty($this->_errors)) {
                $this->pageAlert(tr('There were some problems while attempting to save. Please check below for problem fields.'), Alert::ERROR);
            }
        } elseif (@array_key_exists('delete', $_POST['action'])) {

            /**
             * Prior to deleting an author, provided with the Author ID.
             *
             * @delegate AuthorPreDelete
             * @since Symphony 2.2
             * @param string $context
             * '/system/authors/'
             * @param integer $author_id
             *  The ID of Author ID that is about to be deleted
             */
            Symphony::ExtensionManager()->notifyMembers('AuthorPreDelete', '/system/authors/', array('author_id' => $author_id));

            if (!$isOwner) {
                AuthorManager::delete($author_id);
                redirect(SYMPHONY_URL . '/system/authors/');
            } else {
                $this->pageAlert(tr('You cannot remove yourself as you are the active Author.'), Alert::ERROR);
            }
        }
    }
}
