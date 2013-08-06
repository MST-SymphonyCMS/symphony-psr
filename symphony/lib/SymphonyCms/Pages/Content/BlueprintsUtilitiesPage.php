<?php

namespace SymphonyCms\Pages\Content;

use \SymphonyCms\Symphony;
use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Symphony\DateTimeObj;
use \SymphonyCms\Pages\AdministrationPage;
use \SymphonyCms\Toolkit\PageManager;
use \SymphonyCms\Toolkit\Widget;
use \SymphonyCms\Toolkit\XMLElement;
use \SymphonyCms\Toolkit\XSLTProcess;
use \SymphonyCms\Utilities\General;

/**
 * The Utilities page allows Developers to create Utilities through the
 * Symphony backend. Although most Developers will actually do this through
 * an IDE, this allows a quick way for users to build Symphony within Symphony
 */
class BlueprintsUtilitiesPage extends AdministrationPage
{
    public $_errors = array();
    public $_existing_file;

    public function viewIndex()
    {
        $this->setPageType('table');
        $this->setTitle(tr('%1$s &ndash; %2$s', array(tr('Utilities'), tr('Symphony'))));
        $this->appendSubheading(tr('Utilities'), Widget::Anchor(tr('Create New'), SYMPHONY_URL . '/blueprints/utilities/new/', tr('Create a new utility'), 'create button'));

        $utilities = General::listStructure(UTILITIES, array('xsl'), false, 'asc', UTILITIES);
        $utilities = $utilities['filelist'];

        $aTableHead = array(
            array(tr('Name'), 'col'),
        );

        $aTableBody = array();

        if (!is_array($utilities) || empty($utilities)) {
            $aTableBody = array(
                Widget::TableRow(array(Widget::TableData(tr('None found.'), 'inactive', null, count($aTableHead))), 'odd')
            );
        } else {
            foreach ($utilities as $u) {
                $name = Widget::TableData(
                    Widget::Anchor(
                        $u,
                        SYMPHONY_URL . '/blueprints/utilities/edit/' . str_replace('.xsl', '', $u) . '/')
                );

                $name->appendChild(Widget::Input('items[' . $u . ']', null, 'checkbox'));

                $aTableBody[] = Widget::TableRow(array($name));
            }
        }

        $table = Widget::Table(
            Widget::TableHead($aTableHead),
            null,
            Widget::TableBody($aTableBody),
            'selectable'
        );

        $this->Form->appendChild($table);

        $tableActions = new XMLElement('div');
        $tableActions->setAttribute('class', 'actions');

        $options = array(
            array(null, false, tr('With Selected...')),
            array('delete', false, tr('Delete'), 'confirm'),
        );

        $tableActions->appendChild(Widget::Apply($options));
        $this->Form->appendChild($tableActions);
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
        $this->setPageType('form');
        $this->_existing_file = (isset($this->_context[1]) ? $this->_context[1] . '.xsl' : null);
        $this->Form->setAttribute('class', 'columns');

        $filename = $this->_existing_file;

        // Handle unknown context
        if (!in_array($this->_context[0], array('new', 'edit'))) {
            Administration::instance()->errorPageNotFound();
        }

        // Edit Utility context
        if ($this->_context[0] == 'edit') {
            $file_abs = UTILITIES . '/' . $this->_existing_file;

            if (!is_file($file_abs)) {
                redirect(SYMPHONY_URL . '/blueprints/utilities/new/');
            }

            $fields['name'] = $filename;
            $fields['body'] = @file_get_contents($file_abs);

            $this->Form->setAttribute('action', SYMPHONY_URL . '/blueprints/utilities/edit/' . $this->_context[1] . '/');
        } else {
            $fields['body'] = file_get_contents(PageManager::getTemplate('blueprints.utility'));
        }

        $formHasErrors = (is_array($this->_errors) && !empty($this->_errors));
        if ($formHasErrors) {
            $this->pageAlert(
                tr('An error occurred while processing this form. See below for details.'),
                Alert::ERROR
            );
        }
        // These alerts are only valid if the form doesn't have errors
        if (isset($this->_context[2])) {
            switch ($this->_context[2]) {
                case 'saved':
                    $this->pageAlert(
                        tr('Utility updated at %s.', array(DateTimeObj::getTimeAgo()))
                        . ' <a href="' . SYMPHONY_URL . '/blueprints/utilities/new/" accesskey="c">'
                        . tr('Create another?')
                        . '</a> <a href="' . SYMPHONY_URL . '/blueprints/utilities/" accesskey="a">'
                        . tr('View all Utilities')
                        . '</a>',
                        Alert::SUCCESS
                    );
                    break;
                case 'created':
                    $this->pageAlert(
                        tr('Utility created at %s.', array(DateTimeObj::getTimeAgo()))
                        . ' <a href="' . SYMPHONY_URL . '/blueprints/utilities/new/" accesskey="c">'
                        . tr('Create another?')
                        . '</a> <a href="' . SYMPHONY_URL . '/blueprints/utilities/" accesskey="a">'
                        . tr('View all Utilities')
                        . '</a>',
                        Alert::SUCCESS
                    );
                    break;
            }
        }

        $this->setTitle(tr(($this->_context[0] == 'new' ? '%2$s &ndash; %3$s' : '%1$s &ndash; %2$s &ndash; %3$s'), array($filename, tr('Utilities'), tr('Symphony'))));
        $this->appendSubheading(($this->_context[0] == 'new' ? tr('Untitled') : $filename));
        $this->insertBreadcrumbs(
            array(
                Widget::Anchor(tr('Utilities'), SYMPHONY_URL . '/blueprints/utilities/'),
            )
        );

        if (!empty($_POST)) {
            $fields = $_POST['fields'];
        }

        $fields['body'] = htmlentities($fields['body'], ENT_COMPAT, 'UTF-8');
        $fields['name'] = (isset($fields['name']))? $fields['name'] : null;

        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'primary column');

        $label = Widget::Label(tr('Name'));
        $label->appendChild(Widget::Input('fields[name]', $fields['name']));
        $fieldset->appendChild((isset($this->_errors['name']) ? Widget::Error($label, $this->_errors['name']) : $label));

        $label = Widget::Label(tr('Body'));
        $label->appendChild(Widget::Textarea('fields[body]', 30, 80, $fields['body'], array('class' => 'code')));
        $fieldset->appendChild((isset($this->_errors['body']) ? Widget::Error($label, $this->_errors['body']) : $label));

        $this->Form->appendChild($fieldset);

        $utilities = General::listStructure(UTILITIES, array('xsl'), false, 'asc', UTILITIES);
        $utilities = $utilities['filelist'];

        if (is_array($utilities) && !empty($utilities)) {
            $this->Form->setAttribute('class', 'two columns');

            $div = new XMLElement('div');
            $div->setAttribute('class', 'secondary column');

            $p = new XMLElement('p', tr('Utilities'));
            $p->setAttribute('class', 'label');
            $div->appendChild($p);

            $frame = new XMLElement('div', null, array('class' => 'frame'));

            $ul = new XMLElement('ul');
            $ul->setAttribute('id', 'utilities');

            foreach ($utilities as $util) {
                $li = new XMLElement('li');
                $li->appendChild(Widget::Anchor($util, SYMPHONY_URL . '/blueprints/utilities/edit/' . str_replace('.xsl', '', $util) . '/', null));
                $ul->appendChild($li);
            }

            $frame->appendChild($ul);
            $div->appendChild($frame);

            $this->Form->appendChild($div);
        }

        $div = new XMLElement('div');
        $div->setAttribute('class', 'actions');
        $div->appendChild(Widget::Input('action[save]', ($this->_context[0] == 'edit' ? tr('Save Changes') : tr('Create Utility')), 'submit', array('accesskey' => 's')));

        if ($this->_context[0] == 'edit') {
            $button = new XMLElement('button', tr('Delete'));
            $button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => tr('Delete this utility'), 'type' => 'submit', 'accesskey' => 'd', 'data-message' => tr('Are you sure you want to delete this Utility?')));
            $div->appendChild($button);
        }

        $this->Form->appendChild($div);

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
             *  '/blueprints/utilities/'
             * @param array $checked
             *  An array of the selected rows. The value is usually the ID of the
             *  the associated object.
             */
            Symphony::ExtensionManager()->notifyMembers(
                'CustomActions',
                '/blueprints/utilities/',
                array(
                    'checked' => $checked
                )
            );

            switch ($_POST['with-selected']) {
                case 'delete':
                    $canProceed = true;
                    foreach ($checked as $name) {
                        if (!General::deleteFile(UTILITIES . '/' . $name)) {
                            $this->pageAlert(
                                tr('Failed to delete %s.', array('<code>' . $name . '</code>'))
                                . ' ' . tr('Please check permissions on %s.', array('<code>/workspace/utilities</code>')),
                                Alert::ERROR
                            );
                            $canProceed = false;
                        }
                    }

                    if ($canProceed) {
                        redirect(Administration::instance()->getCurrentPageURL());
                    }

                    break;
            }
        }
    }

    public function actionNew()
    {
        if (array_key_exists('save', $_POST['action']) || array_key_exists('done', $_POST['action'])) {
            return $this->formAction();
        }
    }

    public function actionEdit()
    {
        $this->_existing_file = (isset($this->_context[1]) ? $this->_context[1] . '.xsl' : null);

        if (array_key_exists('save', $_POST['action']) || array_key_exists('done', $_POST['action'])) {
            return $this->formAction();
        } elseif ($this->_context[0] == 'edit' && @array_key_exists('delete', $_POST['action'])) {

            /**
             * Prior to deleting the Utility
             *
             * @delegate UtilityPreDelete
             * @since Symphony 2.2
             * @param string $context
             * '/blueprints/utilities/'
             * @param string $file
             *  The path to the Utility file
             */
            Symphony::ExtensionManager()->notifyMembers('UtilityPreDelete', '/blueprints/utilities/', array('file' => $this->_existing_file));

            General::deleteFile(UTILITIES . '/' . $this->_existing_file);

            redirect(SYMPHONY_URL . '/blueprints/utilities/');
        }
    }

    public function formAction()
    {
        $fields = $_POST['fields'];

        $this->_errors = array();

        if (!isset($fields['name']) || trim($fields['name']) == '') {
            $this->_errors['name'] = tr('Name is a required field.');
        }

        if (!isset($fields['body']) || trim($fields['body']) == '') {
            $this->_errors['body'] = tr('Body is a required field.');
        } elseif (!General::validateXML($fields['body'], $errors, false, new XSLTProcess())) {
            $this->_errors['body'] = tr('This document is not well formed.') . ' ' . tr('The following error was returned:') . ' <code>' . $errors[0]['message'] . '</code>';
        }

        $fields['name'] = Lang::createFilename($fields['name']);
        if (General::right($fields['name'], 4) != '.xsl') {
            $fields['name'] .= '.xsl';
        }

        $file = UTILITIES . '/' . $fields['name'];

        // Duplicate
        if ($this->_context[0] == 'edit' && ($this->_existing_file != $fields['name'] && is_file($file))) {
            $this->_errors['name'] = tr('A Utility with that name already exists. Please choose another.');
        } elseif ($this->_context[0] == 'new' && is_file($file)) {
            $this->_errors['name'] = tr('A Utility with that name already exists. Please choose another.');
        }

        if (empty($this->_errors)) {
            if ($this->_context[0] == 'new') {
                /**
                 * Just before the Utility has been created
                 *
                 * @delegate UtilityPreCreate
                 * @since Symphony 2.2
                 * @param string $context
                 * '/blueprints/utilities/'
                 * @param string $file
                 *  The path to the Utility file
                 * @param string $contents
                 *  The contents of the `$fields['body']`, passed by reference
                 */
                Symphony::ExtensionManager()->notifyMembers('UtilityPreCreate', '/blueprints/utilities/', array('file' => $file, 'contents' => &$fields['body']));
            } else {
                /**
                 * Just before the Utility has been updated
                 *
                 * @delegate UtilityPreEdit
                 * @since Symphony 2.2
                 * @param string $context
                 * '/blueprints/utilities/'
                 * @param string $file
                 *  The path to the Utility file
                 * @param string $contents
                 *  The contents of the `$fields['body']`, passed by reference
                 */
                Symphony::ExtensionManager()->notifyMembers('UtilityPreEdit', '/blueprints/utilities/', array('file' => $file, 'contents' => &$fields['body']));
            }

            // Write the file
            if (!$write = General::writeFile($file, $fields['body'], Symphony::Configuration()->get('write_mode', 'file'))) {
                $this->pageAlert(
                    tr('Utility could not be written to disk.')
                    . ' ' . tr('Please check permissions on %s.', array('<code>/workspace/utilities</code>'))
                    , Alert::ERROR
                );
            } else {
                // Write Successful, add record to the database

                // Remove any existing file if the filename has changed
                if ($this->_existing_file && $file != UTILITIES . '/' . $this->_existing_file) {
                    General::deleteFile(UTILITIES . '/' . $this->_existing_file);
                }

                if ($this->_context[0] == 'new') {
                    /**
                     * Just after the Utility has been written to disk
                     *
                     * @delegate UtilityPostCreate
                     * @since Symphony 2.2
                     * @param string $context
                     * '/blueprints/utilities/'
                     * @param string $file
                     *  The path to the Utility file
                     */
                    Symphony::ExtensionManager()->notifyMembers('UtilityPostCreate', '/blueprints/utilities/', array('file' => $file));
                } else {
                    /**
                     * Just after a Utility has been edited and written to disk
                     *
                     * @delegate UtilityPostEdit
                     * @since Symphony 2.2
                     * @param string $context
                     * '/blueprints/utilities/'
                     * @param string $file
                     *  The path to the Utility file
                     */
                    Symphony::ExtensionManager()->notifyMembers('UtilityPostEdit', '/blueprints/utilities/', array('file' => $file));
                }

                redirect(SYMPHONY_URL . '/blueprints/utilities/edit/'.str_replace('.xsl', '', $fields['name']) . '/'.($this->_context[0] == 'new' ? 'created' : 'saved') . '/');
            }
        }
    }
}
