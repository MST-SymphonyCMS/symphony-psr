<?php

namespace SymphonyCms\Pages\Content;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Symphony\DateTimeObj;
use \SymphonyCms\Exceptions\DatabaseException;
use \SymphonyCms\Pages\AdministrationPage;
use \SymphonyCms\Toolkit\Alert;
use \SymphonyCms\Toolkit\Field;
use \SymphonyCms\Toolkit\FieldManager;
use \SymphonyCms\Toolkit\Entry;
use \SymphonyCms\Toolkit\EntryManager;
use \SymphonyCms\Toolkit\Page;
use \SymphonyCms\Toolkit\SectionManager;
use \SymphonyCms\Toolkit\Sortable;
use \SymphonyCms\Toolkit\Widget;
use \SymphonyCms\Toolkit\XMLElement;

/**
 * The Publish page is where the majority of an Authors time will
 * be spent in Symphony with adding, editing and removing entries
 * from Sections. This Page controls the entries table as well as
 * the Entry creation screens.
 */
class PublishPage extends AdministrationPage
{
    public $_errors = array();

    public function sort(&$sort, &$order, $params)
    {
        $section = $params['current-section'];

        // If `?unsort` is appended to the URL, then sorting information are reverted
        // to their defaults
        if ($params['unsort']) {
            $section->setSortingField($section->getDefaultSortingField(), false);
            $section->setSortingOrder('asc');

            redirect(Symphony::get('Engine')-getCurrentPageURL());
        }

        // By default, sorting information are retrieved from
        // the filesystem and stored inside the `Configuration` object
        if (is_null($sort) && is_null($order)) {
            $sort = $section->getSortingField();
            $order = $section->getSortingOrder();

            // Set the sorting in the `EntryManager` for subsequent use
            EntryManager::setFetchSorting($sort, $order);
        } else {
            // Ensure that this field is infact sortable, otherwise
            // fallback to IDs
            if (($field = FieldManager::fetch($sort)) instanceof Field && !$field->isSortable()) {
                $sort = $section->getDefaultSortingField();
            }

            // If the sort order or direction differs from what is saved,
            // update the config file and reload the page
            if ($sort != $section->getSortingField() || $order != $section->getSortingOrder()) {
                $section->setSortingField($sort, false);
                $section->setSortingOrder($order);

                if ($params['filters']) {
                    $params['filters'] = '?' . trim($params['filters'], '&amp;');
                }

                redirect(Symphony::get('Engine')->getCurrentPageURL() . $params['filters']);
            }
        }

    }

    public function action()
    {
        $this->switchboard('action');
    }

    public function switchboard($type = 'view')
    {
        $function = ($type == 'action' ? 'action' : 'view') . ucfirst($this->_context['page']);

        if (!method_exists($this, $function)) {
            // If there is no action function, just return without doing anything
            if ($type == 'action') {
                return;
            }

            Symphony::get('Engine')->errorPageNotFound();
        }

        $this->$function();
    }

    public function view()
    {
        $this->switchboard();
    }

    public function viewIndex()
    {
        if (!$section_id = SectionManager::fetchIDFromHandle($this->_context['section_handle'])) {
            Symphony::get('Engine')->throwCustomError(
                tr('The Section, %s, could not be found.', array('<code>' . $this->_context['section_handle'] . '</code>')),
                tr('Unknown Section'),
                Page::HTTP_STATUS_NOT_FOUND
            );
        }

        $section = SectionManager::fetch($section_id);

        $this->setPageType('table');
        $this->setTitle(tr('%1$s &ndash; %2$s', array($section->get('name'), tr('Symphony'))));

        $filters = array();
        $filter_querystring = $prepopulate_querystring = $where = $joins = null;
        $current_page = (isset($_REQUEST['pg']) && is_numeric($_REQUEST['pg']) ? max(1, intval($_REQUEST['pg'])) : 1);

        if (isset($_REQUEST['filter'])) {
            // legacy implementation, convert single filter to an array
            // split string in the form ?filter=handle:value
            if (!is_array($_REQUEST['filter'])) {
                list($field_handle, $filter_value) = explode(':', $_REQUEST['filter'], 2);
                $filters[$field_handle] = rawurldecode($filter_value);
            } else {
                $filters = $_REQUEST['filter'];
            }

            foreach ($filters as $handle => $value) {
                $field_id = FieldManager::fetchFieldIDFromElementName(
                    Symphony::get('Database')->cleanValue($handle),
                    $section->get('id')
                );

                $field = FieldManager::fetch($field_id);

                if ($field instanceof Field) {
                    // For deprecated reasons, call the old, typo'd function name until the switch to the
                    // properly named buildDSRetrievalSQL function.
                    $field->buildDSRetrivalSQL(array($value), $joins, $where, false);
                    $filter_querystring .= sprintf("filter[%s]=%s&amp;", $handle, rawurlencode($value));
                    $prepopulate_querystring .= sprintf("prepopulate[%d]=%s&amp;", $field_id, rawurlencode($value));
                } else {
                    unset($filters[$handle]);
                }
            }

            $filter_querystring = preg_replace("/&amp;$/", '', $filter_querystring);
            $prepopulate_querystring = preg_replace("/&amp;$/", '', $prepopulate_querystring);
        }

        Sortable::initialize(
            $this,
            $entries,
            $sort,
            $order,
            array(
                'current-section' => $section,
                'filters' => ($filter_querystring ? "&amp;" . $filter_querystring : ''),
                'unsort' => isset($_REQUEST['unsort'])
            )
        );

        $this->Form->setAttribute('action', Symphony::get('Engine')->getCurrentPageURL(). '?pg=' . $current_page.($filter_querystring ? "&amp;" . $filter_querystring : ''));

        $subheading_buttons = array(
            Widget::Anchor(tr('Create New'), Symphony::get('Engine')->getCurrentPageURL().'new/'.($prepopulate_querystring ? '?' . $prepopulate_querystring : ''), tr('Create a new entry'), 'create button', null, array('accesskey' => 'c'))
        );

        // Only show the Edit Section button if the Author is a developer. #938 ^BA
        if (Symphony::get('Author')->isDeveloper()) {
            array_unshift($subheading_buttons, Widget::Anchor(tr('Edit Section'), SYMPHONY_URL . '/blueprints/sections/edit/' . $section_id . '/', tr('Edit Section Configuration'), 'button'));
        }

        $this->appendSubheading($section->get('name'), $subheading_buttons);

        /**
         * Allows adjustments to be made to the SQL where and joins statements
         * before they are used to fetch the entries for the page
         *
         * @delegate AdjustPublishFiltering
         * @since Symphony 2.3.3
         * @param string $context
         * '/publish/'
         * @param integer $section_id
         * An array of the current columns, passed by reference
         * @param string $where
         * The current where statement, or null if not set
         * @param string $joins
         */
        Symphony::get('ExtensionManager')->notifyMembers('AdjustPublishFiltering', '/publish/', array('section-id' => $section_id, 'where' => &$where, 'joins' => &$joins));

        // Check that the filtered query fails that the filter is dropped and an
        // error is logged. #841 ^BA
        try {
            $entries = EntryManager::fetchByPage($current_page, $section_id, Symphony::get('Configuration')->get('pagination_maximum_rows', 'symphony'), $where, $joins);
        } catch (DatabaseException $ex) {
            $this->pageAlert(tr('An error occurred while retrieving filtered entries. Showing all entries instead.'), Alert::ERROR);
            $filter_querystring = null;
            Symphony::Log()->pushToLog(
                sprintf(
                    '%s - %s%s%s',
                    $section->get('name') . ' Publish Index',
                    $ex->getMessage(),
                    ($ex->getFile() ? " in file " .  $ex->getFile() : null),
                    ($ex->getLine() ? " on line " . $ex->getLine() : null)
                ),
                E_NOTICE,
                true
            );
            $entries = EntryManager::fetchByPage($current_page, $section_id, Symphony::get('Configuration')->get('pagination_maximum_rows', 'symphony'));
        }

        $visible_columns = $section->fetchVisibleColumns();
        $columns = array();

        if (is_array($visible_columns) && !empty($visible_columns)) {

            foreach ($visible_columns as $column) {
                $columns[] = array(
                    'label' => $column->get('label'),
                    'sortable' => $column->isSortable(),
                    'handle' => $column->get('id'),
                    'attrs' => array(
                        'id' => 'field-' . $column->get('id'),
                        'class' => 'field-' . $column->get('type')
                    )
                );
            }
        } else {
            $columns[] = array(
                'label' => tr('ID'),
                'sortable' => true,
                'handle' => 'id'
            );
        }

        $aTableHead = Sortable::buildTableHeaders(
            $columns,
            $sort,
            $order,
            ($filter_querystring) ? "&amp;" . $filter_querystring : ''
        );

        $child_sections = array();
        $associated_sections = $section->fetchAssociatedSections(true);

        if (is_array($associated_sections) && !empty($associated_sections)) {
            foreach ($associated_sections as $key => $as) {
                $child_sections[$key] = SectionManager::fetch($as['child_section_id']);
                $aTableHead[] = array($child_sections[$key]->get('name'), 'col');
            }
        }

        /**
         * Allows the creation of custom table columns for each entry. Called
         * after all the Section Visible columns have been added as well
         * as the Section Associations
         *
         * @delegate AddCustomPublishColumn
         * @since Symphony 2.2
         * @param string $context
         * '/publish/'
         * @param array $tableHead
         * An array of the current columns, passed by reference
         * @param integer $section_id
         * The current Section ID
         */
        Symphony::get('ExtensionManager')->notifyMembers('AddCustomPublishColumn', '/publish/', array('tableHead' => &$aTableHead, 'section_id' => $section->get('id')));

        // Table Body
        $aTableBody = array();

        if (!is_array($entries['records']) || empty($entries['records'])) {

            $aTableBody = array(
                Widget::TableRow(array(Widget::TableData(tr('None found.'), 'inactive', null, count($aTableHead))), 'odd')
            );
        } else {
            $field_pool = array();
            if (is_array($visible_columns) && !empty($visible_columns)) {
                foreach ($visible_columns as $column) {
                    $field_pool[$column->get('id')] = $column;
                }
            }
            $link_column = array_reverse($visible_columns);
            $link_column = end($link_column);
            reset($visible_columns);

            foreach( $entries['records'] as $entry) {
                $tableData = array();

                // Setup each cell
                if (!is_array($visible_columns) || empty($visible_columns)) {
                    $tableData[] = Widget::TableData(Widget::Anchor($entry->get('id'), Symphony::get('Engine')->getCurrentPageURL() . 'edit/' . $entry->get('id') . '/'));
                } else {
                    $link = Widget::Anchor(
                        tr('None'),
                        Symphony::get('Engine')->getCurrentPageURL() . 'edit/' . $entry->get('id') . '/'.($filter_querystring ? '?' . $prepopulate_querystring : ''),
                        $entry->get('id'),
                        'content'
                    );

                    foreach ($visible_columns as $position => $column) {
                        $data = $entry->getData($column->get('id'));
                        $field = $field_pool[$column->get('id')];

                        $value = $field->prepareTableValue($data, ($column == $link_column) ? $link : null, $entry->get('id'));

                        if (!is_object($value) && (strlen(trim($value)) == 0 || $value == tr('None'))) {
                            $value = ($position == 0 ? $link->generate() : tr('None'));
                        }

                        if ($value == tr('None')) {
                            $tableData[] = Widget::TableData($value, 'inactive field-' . $column->get('type') . ' field-' . $column->get('id'));
                        } else {
                            $tableData[] = Widget::TableData($value, 'field-' . $column->get('type') . ' field-' . $column->get('id'));
                        }

                        unset($field);
                    }
                }

                if (is_array($child_sections) && !empty($child_sections)) {
                    foreach ($child_sections as $key => $as) {

                        $field = FieldManager::fetch((int)$associated_sections[$key]['child_section_field_id']);

                        $parent_section_field_id = (int)$associated_sections[$key]['parent_section_field_id'];

                        if (!is_null($parent_section_field_id)) {
                            $search_value = $field->fetchAssociatedEntrySearchValue(
                                $entry->getData($parent_section_field_id),
                                $parent_section_field_id,
                                $entry->get('id')
                            );
                        } else {
                            $search_value = $entry->get('id');
                        }

                        if (!is_array($search_value)) {
                            $associated_entry_count = $field->fetchAssociatedEntryCount($search_value);

                            $tableData[] = Widget::TableData(
                                Widget::Anchor(
                                    sprintf('%d &rarr;', max(0, intval($associated_entry_count))),
                                    sprintf(
                                        '%s/publish/%s/?filter[%s]=%s',
                                        SYMPHONY_URL,
                                        $as->get('handle'),
                                        $field->get('element_name'),
                                        rawurlencode($search_value)
                                    ),
                                    $entry->get('id'),
                                    'content'
                                )
                            );
                        }
                    }
                }

                /**
                 * Allows Extensions to inject custom table data for each Entry
                 * into the Publish Index
                 *
                 * @delegate AddCustomPublishColumnData
                 * @since Symphony 2.2
                 * @param string $context
                 * '/publish/'
                 * @param array $tableData
                 *  An array of `Widget::TableData`, passed by reference
                 * @param integer $section_id
                 *  The current Section ID
                 * @param Entry $entry_id
                 *  The entry object, please note that this is by error and this will
                 *  be removed in Symphony 2.4. The entry object is available in
                 *  the 'entry' key as of Symphony 2.3.1.
                 * @param Entry $entry
                 *  The entry object for this row
                 */
                Symphony::get('ExtensionManager')->notifyMembers(
                    'AddCustomPublishColumnData',
                    '/publish/',
                    array(
                        'tableData' => &$tableData,
                        'section_id' => $section->get('id'),
                        'entry_id' => $entry,
                        'entry' => $entry
                    )
                );

                $tableData[count($tableData) - 1]->appendChild(Widget::Input('items['.$entry->get('id').']', null, 'checkbox'));

                // Add a row to the body array, assigning each cell to the row
                $aTableBody[] = Widget::TableRow($tableData, null, 'id-' . $entry->get('id'));
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
            array('delete', false, tr('Delete'), 'confirm', null, array(
                'data-message' => tr('Are you sure you want to delete the selected entries?')
            ))
        );

        $toggable_fields = $section->fetchToggleableFields();

        if (is_array($toggable_fields) && !empty($toggable_fields)) {

            $index = 2;

            foreach ($toggable_fields as $field) {

                $toggle_states = $field->getToggleStates();

                if (is_array($toggle_states)) {

                    $options[$index] = array('label' => tr('Set %s', array($field->get('label'))), 'options' => array());

                    foreach ($toggle_states as $value => $state) {

                        $options[$index]['options'][] = array('toggle-' . $field->get('id') . '-' . $value, false, $state);
                    }
                }

                $index++;
            }
        }

        /**
         * Allows an extension to modify the existing options for this page's
         * With Selected menu. If the `$options` parameter is an empty array,
         * the 'With Selected' menu will not be rendered.
         *
         * @delegate AddCustomActions
         * @since Symphony 2.3.2
         * @param string $context
         * '/publish/'
         * @param array $options
         *  An array of arrays, where each child array represents an option
         *  in the With Selected menu. Options should follow the same format
         *  expected by `Widget::selectBuildOption`. Passed by reference.
         */
        Symphony::get('ExtensionManager')->notifyMembers(
            'AddCustomActions',
            '/publish/',
            array(
                'options' => &$options
            )
        );

        if (!empty($options)) {
            $tableActions->appendChild(Widget::Apply($options));
            $this->Form->appendChild($tableActions);
        }

        if ($entries['total-pages'] > 1) {
            $ul = new XMLElement('ul');
            $ul->setAttribute('class', 'page');

            // First
            $li = new XMLElement('li');
            if ($current_page > 1) {
                $li->appendChild(Widget::Anchor(tr('First'), Symphony::get('Engine')->getCurrentPageURL(). '?pg=1'.($filter_querystring ? "&amp;" . $filter_querystring : '')));
            } else {
                $li->setValue(tr('First'));
            }

            $ul->appendChild($li);

            // Previous
            $li = new XMLElement('li');
            if ($current_page > 1) {
                $li->appendChild(Widget::Anchor(tr('&larr; Previous'), Symphony::get('Engine')->getCurrentPageURL(). '?pg=' . ($current_page - 1).($filter_querystring ? "&amp;" . $filter_querystring : '')));
            } else {
                $li->setValue(tr('&larr; Previous'));
            }
            $ul->appendChild($li);

            // Summary
            $li = new XMLElement('li');

            $li->setAttribute(
                'title',
                tr(
                    'Viewing %1$s - %2$s of %3$s entries',
                    array(
                        $entries['start'],
                        ($current_page != $entries['total-pages']) ? $current_page * Symphony::get('Configuration')->get('pagination_maximum_rows', 'symphony') : $entries['total-entries'],
                        $entries['total-entries']
                    )
                )
            );

            $pgform = Widget::Form(Symphony::get('Engine')->getCurrentPageURL(), 'get', 'paginationform');
            $pgmax = max($current_page, $entries['total-pages']);
            $pgform->appendChild(
                Widget::Input(
                    'pg',
                    null,
                    'text',
                    array(
                        'data-active' => tr('Go to page …'),
                        'data-inactive' => tr('Page %1$s of %2$s', array((string)$current_page, $pgmax)),
                        'data-max' => $pgmax
                    )
                )
            );

            $li->appendChild($pgform);
            $ul->appendChild($li);

            // Next
            $li = new XMLElement('li');
            if ($current_page < $entries['total-pages']) {
                $li->appendChild(Widget::Anchor(tr('Next &rarr;'), Symphony::get('Engine')->getCurrentPageURL(). '?pg=' . ($current_page + 1).($filter_querystring ? "&amp;" . $filter_querystring : '')));
            } else {
                $li->setValue(tr('Next &rarr;'));
            }
            $ul->appendChild($li);

            // Last
            $li = new XMLElement('li');
            if ($current_page < $entries['total-pages']) {
                $li->appendChild(Widget::Anchor(tr('Last'), Symphony::get('Engine')->getCurrentPageURL(). '?pg=' . $entries['total-pages'].($filter_querystring ? "&amp;" . $filter_querystring : '')));
            } else {
                $li->setValue(tr('Last'));
            }
            $ul->appendChild($li);

            $this->Contents->appendChild($ul);
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
             *  '/publish/'
             * @param array $checked
             *  An array of the selected rows. The value is usually the ID of the
             *  the associated object.
             */
            Symphony::get('ExtensionManager')->notifyMembers('CustomActions', '/publish/', array(
                'checked' => $checked
            ));

            switch ($_POST['with-selected']) {
                case 'delete':
                    /**
                     * Prior to deletion of entries. An array of Entry ID's is provided which
                     * can be manipulated. This delegate was renamed from `Delete` to `EntryPreDelete`
                     * in Symphony 2.3.
                     *
                     * @delegate EntryPreDelete
                     * @param string $context
                     * '/publish/'
                     * @param array $entry_id
                     *  An array of Entry ID's passed by reference
                     */
                    Symphony::get('ExtensionManager')->notifyMembers('EntryPreDelete', '/publish/', array('entry_id' => &$checked));

                    EntryManager::delete($checked);

                    /**
                     * After the deletion of entries, this delegate provides an array of Entry ID's
                     * that were deleted.
                     *
                     * @since Symphony 2.3
                     * @delegate EntryPostDelete
                     * @param string $context
                     * '/publish/'
                     * @param array $entry_id
                     *  An array of Entry ID's that were deleted.
                     */
                    Symphony::get('ExtensionManager')->notifyMembers('EntryPostDelete', '/publish/', array('entry_id' => $checked));

                    redirect($_SERVER['REQUEST_URI']);
                default:
                    list($option, $field_id, $value) = explode('-', $_POST['with-selected'], 3);
                    if ($option == 'toggle') {
                        $field = FieldManager::fetch($field_id);
                        $fields = array($field->get('element_name') => $value);

                        $section = SectionManager::fetch($field->get('parent_section'));

                        foreach ($checked as $entry_id) {
                            $entry = EntryManager::fetch($entry_id);
                            $existing_data = $entry[0]->getData($field_id);
                            $entry[0]->setData($field_id, $field->toggleFieldData(is_array($existing_data) ? $existing_data : array(), $value, $entry_id));

                            /**
                             * Just prior to editing of an Entry
                             *
                             * @delegate EntryPreEdit
                             * @param string $context
                             * '/publish/edit/'
                             * @param Section $section
                             * @param Entry $entry
                             * @param array $fields
                             */
                            Symphony::get('ExtensionManager')->notifyMembers(
                                'EntryPreEdit',
                                '/publish/edit/',
                                array(
                                    'section' => $section,
                                    'entry' => &$entry[0],
                                    'fields' => $fields
                                )
                            );

                            $entry[0]->commit();

                            /**
                             * Editing an entry. Entry object is provided.
                             *
                             * @delegate EntryPostEdit
                             * @param string $context
                             * '/publish/edit/'
                             * @param Section $section
                             * @param Entry $entry
                             * @param array $fields
                             */
                            Symphony::get('ExtensionManager')->notifyMembers(
                                'EntryPostEdit',
                                '/publish/edit/',
                                array(
                                    'section' => $section,
                                    'entry' => $entry[0],
                                    'fields' => $fields
                                )
                            );
                        }

                        redirect($_SERVER['REQUEST_URI']);
                    }

                    break;
            }
        }
    }

    public function viewNew()
    {
        if (!$section_id = SectionManager::fetchIDFromHandle($this->_context['section_handle'])) {
            Symphony::get('Engine')->throwCustomError(
                tr('The Section, %s, could not be found.', array('<code>' . $this->_context['section_handle'] . '</code>')),
                tr('Unknown Section'),
                Page::HTTP_STATUS_NOT_FOUND
            );
        }

        $section = SectionManager::fetch($section_id);

        $this->setPageType('form');
        $this->Form->setAttribute('enctype', 'multipart/form-data');
        $this->Form->setAttribute('class', 'two columns');
        $this->setTitle(tr('%1$s &ndash; %2$s', array($section->get('name'), tr('Symphony'))));

        // Only show the Edit Section button if the Author is a developer. #938 ^BA
        if (Symphony::get('Author')->isDeveloper()) {
            $this->appendSubheading(
                tr('Untitled'),
                Widget::Anchor(tr('Edit Section'), SYMPHONY_URL . '/blueprints/sections/edit/' . $section_id . '/', tr('Edit Section Configuration'), 'button')
            );
        } else {
            $this->appendSubheading(tr('Untitled'));
        }

        // Build filtered breadcrumb [#1378}
        if (isset($_REQUEST['prepopulate'])) {
            $link = '?';
            foreach ($_REQUEST['prepopulate'] as $field_id => $value) {
                $handle = FieldManager::fetchHandleFromID($field_id);
                $link .= "filter[$handle]=$value&amp;";
            }
            $link = preg_replace("/&amp;$/", '', $link);
        } else {
            $link = '';
        }

        $this->insertBreadcrumbs(
            array(
                Widget::Anchor($section->get('name'), SYMPHONY_URL . '/publish/' . $this->_context['section_handle'] . '/' . $link),
            )
        );

        $this->Form->appendChild(Widget::Input('MAX_FILE_SIZE', Symphony::get('Configuration')->get('max_upload_size', 'admin'), 'hidden'));

        // If there is post data floating around, due to errors, create an entry object
        if (isset($_POST['fields'])) {
            $entry = EntryManager::create();
            $entry->set('section_id', $section_id);
            $entry->setDataFromPost($_POST['fields'], $error, true);
        } else {
            // Brand new entry, so need to create some various objects
            $entry = EntryManager::create();
            $entry->set('section_id', $section_id);
        }

        // Check if there is a field to prepopulate
        if (isset($_REQUEST['prepopulate'])) {
            foreach ($_REQUEST['prepopulate'] as $field_id => $value) {
                $this->Form->prependChild(
                    Widget::Input(
                        "prepopulate[{$field_id}]",
                        rawurlencode($value),
                        'hidden'
                    )
                );

                // The actual pre-populating should only happen if there is not existing fields post data
                if (!isset($_POST['fields']) && $field = FieldManager::fetch($field_id)) {
                    $entry->setData(
                        $field->get('id'),
                        $field->processRawFieldData($value, $error, $message, true)
                    );
                }
            }
        }

        $primary = new XMLElement('fieldset');
        $primary->setAttribute('class', 'primary column');

        $sidebar_fields = $section->fetchFields(null, 'sidebar');
        $main_fields = $section->fetchFields(null, 'main');

        if ((!is_array($main_fields) || empty($main_fields)) && (!is_array($sidebar_fields) || empty($sidebar_fields))) {
            $message = tr('Fields must be added to this section before an entry can be created.');

            if (Symphony::get('Author')->isDeveloper()) {
                $message .= ' <a href="' . SYMPHONY_URL . '/blueprints/sections/edit/' . $section->get('id') . '/" accesskey="c">'
                . tr('Add fields')
                . '</a>';
            }

            $this->pageAlert($message, Alert::ERROR);
        } else {
            if (is_array($main_fields) && !empty($main_fields)) {
                foreach ($main_fields as $field) {
                    $primary->appendChild($this->wrapFieldWithDiv($field, $entry));
                }

                $this->Form->appendChild($primary);
            }

            if (is_array($sidebar_fields) && !empty($sidebar_fields)) {
                $sidebar = new XMLElement('fieldset');
                $sidebar->setAttribute('class', 'secondary column');

                foreach ($sidebar_fields as $field) {
                    $sidebar->appendChild($this->wrapFieldWithDiv($field, $entry));
                }

                $this->Form->appendChild($sidebar);
            }

            $div = new XMLElement('div');
            $div->setAttribute('class', 'actions');
            $div->appendChild(Widget::Input('action[save]', tr('Create Entry'), 'submit', array('accesskey' => 's')));

            $this->Form->appendChild($div);

            // Create a Drawer for Associated Sections
            $this->prepareAssociationsDrawer($section);
        }
    }

    public function actionNew()
    {
        if (array_key_exists('save', $_POST['action']) || array_key_exists("done", $_POST['action'])) {
            $section_id = SectionManager::fetchIDFromHandle($this->_context['section_handle']);

            if (!$section = SectionManager::fetch($section_id)) {
                Symphony::get('Engine')->throwCustomError(
                    tr('The Section, %s, could not be found.', array('<code>' . $this->_context['section_handle'] . '</code>')),
                    tr('Unknown Section'),
                    Page::HTTP_STATUS_NOT_FOUND
                );
            }

            $entry = EntryManager::create();
            $entry->set('author_id', Symphony::get('Author')->get('id'));
            $entry->set('section_id', $section_id);
            $entry->set('creation_date', DateTimeObj::get('c'));
            $entry->set('modification_date', DateTimeObj::get('c'));

            $fields = $_POST['fields'];

            // Combine FILES and POST arrays, indexed by their custom field handles
            if (isset($_FILES['fields'])) {
                $filedata = General::processFilePostData($_FILES['fields']);

                foreach ($filedata as $handle => $data) {
                    if (!isset($fields[$handle])) {
                        $fields[$handle] = $data;
                    } elseif (isset($data['error']) && $data['error'] == UPLOAD_ERR_NO_FILE) {
                        $fields[$handle] = null;
                    } else {
                        foreach ($data as $ii => $d) {
                            if (isset($d['error']) && $d['error'] == UPLOAD_ERR_NO_FILE) {
                                $fields[$handle][$ii] = null;
                            } elseif (is_array($d) && !empty($d)) {
                                foreach ($d as $key => $val) {
                                    $fields[$handle][$ii][$key] = $val;
                                }
                            }
                        }
                    }
                }
            }

            // Initial checks to see if the Entry is ok
            if (__ENTRY_FIELD_ERROR__ == $entry->checkPostData($fields, $this->_errors)) {
                $this->pageAlert(tr('Some errors were encountered while attempting to save.'), Alert::ERROR);
            } elseif (__ENTRY_OK__ != $entry->setDataFromPost($fields, $errors)) {
                // Secondary checks, this will actually process the data and attempt to save
                foreach ($errors as $field_id => $message) {
                    $this->pageAlert($message, Alert::ERROR);
                }
            } else {
                // Everything is awesome. Dance.
                /**
                 * Just prior to creation of an Entry
                 *
                 * @delegate EntryPreCreate
                 * @param string $context
                 * '/publish/new/'
                 * @param Section $section
                 * @param Entry $entry
                 * @param array $fields
                 */
                Symphony::get('ExtensionManager')->notifyMembers('EntryPreCreate', '/publish/new/', array('section' => $section, 'entry' => &$entry, 'fields' => &$fields));

                // Check to see if the dancing was premature
                if (!$entry->commit()) {
                    defineSafe('__SYM_DB_INSERT_FAILED__', true);
                    $this->pageAlert(null, Alert::ERROR);
                } else {
                    /**
                     * Creation of an Entry. New Entry object is provided.
                     *
                     * @delegate EntryPostCreate
                     * @param string $context
                     * '/publish/new/'
                     * @param Section $section
                     * @param Entry $entry
                     * @param array $fields
                     */
                    Symphony::get('ExtensionManager')->notifyMembers('EntryPostCreate', '/publish/new/', array('section' => $section, 'entry' => $entry, 'fields' => $fields));

                    $prepopulate_querystring = '';

                    if (isset($_POST['prepopulate'])) {
                        foreach ($_POST['prepopulate'] as $field_id => $value) {
                            $prepopulate_querystring .= sprintf("prepopulate[%s]=%s&", $field_id, rawurldecode($value));
                        }
                        $prepopulate_querystring = trim($prepopulate_querystring, '&');
                    }

                    redirect(
                        sprintf(
                            '%s/publish/%s/edit/%d/created/%s',
                            SYMPHONY_URL,
                            $this->_context['section_handle'],
                            $entry->get('id'),
                            (!empty($prepopulate_querystring) ? "?" . $prepopulate_querystring : null)
                        )
                    );
                }
            }
        }
    }

    public function viewEdit()
    {
        if (!$section_id = SectionManager::fetchIDFromHandle($this->_context['section_handle'])) {
            Symphony::get('Engine')->throwCustomError(
                tr('The Section, %s, could not be found.', array('<code>' . $this->_context['section_handle'] . '</code>')),
                tr('Unknown Section'),
                Page::HTTP_STATUS_NOT_FOUND
            );
        }

        $section = SectionManager::fetch($section_id);
        $entry_id = intval($this->_context['entry_id']);
        $base = '/publish/'.$this->_context['section_handle'] . '/';
        $new_link = $base . 'new/';
        $filter_link = $base;

        EntryManager::setFetchSorting('id', 'DESC');

        if (!$existingEntry = EntryManager::fetch($entry_id)) {
            Symphony::get('Engine')->throwCustomError(
                tr('Unknown Entry'),
                tr('The Entry, %s, could not be found.', array($entry_id)),
                Page::HTTP_STATUS_NOT_FOUND
            );
        }
        $existingEntry = $existingEntry[0];

        // If there is post data floating around, due to errors, create an entry object
        if (isset($_POST['fields'])) {
            $fields = $_POST['fields'];

            $entry = EntryManager::create();
            $entry->set('id', $entry_id);
            $entry->set('author_id', $existingEntry->get('author_id'));
            $entry->set('section_id', $existingEntry->get('section_id'));
            $entry->set('creation_date', $existingEntry->get('creation_date'));
            $entry->set('modification_date', $existingEntry->get('modification_date'));
            $entry->setDataFromPost($fields, $errors, true);
        } else {
            // Editing an entry, so need to create some various objects
            $entry = $existingEntry;
            $fields = array();

            if (!$section) {
                $section = SectionManager::fetch($entry->get('section_id'));
            }
        }

        /**
         * Just prior to rendering of an Entry edit form.
         *
         * @delegate EntryPreRender
         * @param string $context
         * '/publish/edit/'
         * @param Section $section
         * @param Entry $entry
         * @param array $fields
         */
        Symphony::get('ExtensionManager')->notifyMembers(
            'EntryPreRender',
            '/publish/edit/',
            array(
                'section' => $section,
                'entry' => &$entry,
                'fields' => $fields
            )
        );

        // Iterate over the `prepopulate` parameters to build a URL
        // to remember this state for Create New, View all Entries and
        // Breadcrumb links. If `prepopulate` doesn't exist, this will
        // just use the standard pages (ie. no filtering)
        if (isset($_REQUEST['prepopulate'])) {
            $new_link .= '?';
            $filter_link .= '?';

            foreach ($_REQUEST['prepopulate'] as $field_id => $value) {
                $new_link .= "prepopulate[$field_id]=$value&amp;";
                $field_name = FieldManager::fetchHandleFromID($field_id);
                $filter_link .= "filter[$field_name]=$value&amp;";
            }
            $new_link = preg_replace("/&amp;$/", '', $new_link);
            $filter_link = preg_replace("/&amp;$/", '', $filter_link);
        }

        if (isset($this->_context['flag'])) {
            // These flags are only relevant if there are no errors
            if (empty($this->_errors)) {
                switch ($this->_context['flag']) {
                    case 'saved':
                        $this->pageAlert(
                            tr('Entry updated at %s.', array(DateTimeObj::getTimeAgo()))
                            . ' <a href="' . SYMPHONY_URL . $new_link . '" accesskey="c">'
                            . tr('Create another?')
                            . '</a> <a href="' . SYMPHONY_URL . $filter_link . '" accesskey="a">'
                            . tr('View all Entries')
                            . '</a>',
                            Alert::SUCCESS
                        );
                        break;
                    case 'created':
                        $this->pageAlert(
                            tr('Entry created at %s.', array(DateTimeObj::getTimeAgo()))
                            . ' <a href="' . SYMPHONY_URL . $new_link . '" accesskey="c">'
                            . tr('Create another?')
                            . '</a> <a href="' . SYMPHONY_URL . $filter_link . '" accesskey="a">'
                            . tr('View all Entries')
                            . '</a>',
                            Alert::SUCCESS
                        );
                        break;
                }
            }
        }

        // Determine the page title
        $field_id = Symphony::get('Database')->fetchVar('id', 0, "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = '".$section->get('id')."' ORDER BY `sortorder` LIMIT 1");
        if (!is_null($field_id)) {
            $field = FieldManager::fetch($field_id);
        }

        if ($field) {
            $title = trim(strip_tags($field->prepareTableValue($existingEntry->getData($field->get('id')), null, $entry_id)));
        } else {
            $title = '';
        }

        if (trim($title) == '') {
            $title = tr('Untitled');
        }

        // Check if there is a field to prepopulate
        if (isset($_REQUEST['prepopulate'])) {
            foreach ($_REQUEST['prepopulate'] as $field_id => $value) {
                $this->Form->prependChild(
                    Widget::Input(
                        "prepopulate[{$field_id}]",
                        rawurlencode($value),
                        'hidden'
                    )
                );
            }
        }

        $this->setPageType('form');
        $this->Form->setAttribute('enctype', 'multipart/form-data');
        $this->Form->setAttribute('class', 'two columns');
        $this->setTitle(tr('%1$s &ndash; %2$s &ndash; %3$s', array($title, $section->get('name'), tr('Symphony'))));

        // Only show the Edit Section button if the Author is a developer. #938 ^BA
        if (Symphony::get('Author')->isDeveloper()) {
            $this->appendSubheading(
                $title,
                Widget::Anchor(tr('Edit Section'), SYMPHONY_URL . '/blueprints/sections/edit/' . $section_id . '/', tr('Edit Section Configuration'), 'button')
            );
        } else {
            $this->appendSubheading($title);
        }

        $this->insertBreadcrumbs(
            array(
                Widget::Anchor($section->get('name'), SYMPHONY_URL . (isset($filter_link) ? $filter_link : $base)),
            )
        );

        $this->Form->appendChild(Widget::Input('MAX_FILE_SIZE', Symphony::get('Configuration')->get('max_upload_size', 'admin'), 'hidden'));

        $primary = new XMLElement('fieldset');
        $primary->setAttribute('class', 'primary column');

        $sidebar_fields = $section->fetchFields(null, 'sidebar');
        $main_fields = $section->fetchFields(null, 'main');

        if ((!is_array($main_fields) || empty($main_fields)) && (!is_array($sidebar_fields) || empty($sidebar_fields))) {
            $message = tr('Fields must be added to this section before an entry can be created.');

            if (Symphony::get('Author')->isDeveloper()) {
                $message .= ' <a href="' . SYMPHONY_URL . '/blueprints/sections/edit/' . $section->get('id') . '/" accesskey="c">'
                . tr('Add fields')
                . '</a>';
            }

            $this->pageAlert($message, Alert::ERROR);
        } else {
            if (is_array($main_fields) && !empty($main_fields)) {
                foreach ($main_fields as $field) {
                    $primary->appendChild($this->wrapFieldWithDiv($field, $entry));
                }

                $this->Form->appendChild($primary);
            }

            if(is_array($sidebar_fields) && !empty($sidebar_fields)){
                $sidebar = new XMLElement('fieldset');
                $sidebar->setAttribute('class', 'secondary column');

                foreach($sidebar_fields as $field){
                    $sidebar->appendChild($this->wrapFieldWithDiv($field, $entry));
                }

                $this->Form->appendChild($sidebar);
            }

            $div = new XMLElement('div');
            $div->setAttribute('class', 'actions');
            $div->appendChild(Widget::Input('action[save]', tr('Save Changes'), 'submit', array('accesskey' => 's')));

            $button = new XMLElement('button', tr('Delete'));
            $button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => tr('Delete this entry'), 'type' => 'submit', 'accesskey' => 'd', 'data-message' => tr('Are you sure you want to delete this entry?')));
            $div->appendChild($button);

            $this->Form->appendChild($div);

            // Create a Drawer for Associated Sections
            $this->prepareAssociationsDrawer($section);
        }
    }

    public function actionEdit()
    {
        $entry_id = intval($this->_context['entry_id']);

        if (@array_key_exists('save', $_POST['action']) || @array_key_exists("done", $_POST['action'])) {
            if (!$ret = EntryManager::fetch($entry_id)) {
                Symphony::get('Engine')->throwCustomError(
                    tr('The Entry, %s, could not be found.', array($entry_id)),
                    tr('Unknown Entry'),
                    Page::HTTP_STATUS_NOT_FOUND
                );
            }
            $entry = $ret[0];

            $section = SectionManager::fetch($entry->get('section_id'));

            $post = General::getPostData();
            $fields = $post['fields'];

            // Initial checks to see if the Entry is ok
            if (__ENTRY_FIELD_ERROR__ == $entry->checkPostData($fields, $this->_errors)) {
                $this->pageAlert(tr('Some errors were encountered while attempting to save.'), Alert::ERROR);
            } elseif (__ENTRY_OK__ != $entry->setDataFromPost($fields, $errors)) {
                // Secondary checks, this will actually process the data and attempt to save
                foreach ($errors as $field_id => $message) {
                    $this->pageAlert($message, Alert::ERROR);
                }
            } else {
                // Everything is awesome. Dance.
                /**
                 * Just prior to editing of an Entry.
                 *
                 * @delegate EntryPreEdit
                 * @param string $context
                 * '/publish/edit/'
                 * @param Section $section
                 * @param Entry $entry
                 * @param array $fields
                 */
                Symphony::get('ExtensionManager')->notifyMembers('EntryPreEdit', '/publish/edit/', array('section' => $section, 'entry' => &$entry, 'fields' => $fields));

                // Check to see if the dancing was premature
                if (!$entry->commit()) {
                    defineSafe('__SYM_DB_INSERT_FAILED__', true);
                    $this->pageAlert(null, Alert::ERROR);
                } else {
                    /**
                     * Just after the editing of an Entry
                     *
                     * @delegate EntryPostEdit
                     * @param string $context
                     * '/publish/edit/'
                     * @param Section $section
                     * @param Entry $entry
                     * @param array $fields
                     */
                    Symphony::get('ExtensionManager')->notifyMembers('EntryPostEdit', '/publish/edit/', array('section' => $section, 'entry' => $entry, 'fields' => $fields));

                    $prepopulate_querystring = '';
                    if (isset($_POST['prepopulate'])) {
                        foreach ($_POST['prepopulate'] as $field_id => $value) {
                            $prepopulate_querystring .= sprintf("prepopulate[%s]=%s&", $field_id, $value);
                        }
                        $prepopulate_querystring = trim($prepopulate_querystring, '&');
                    }

                    redirect(
                        sprintf(
                            '%s/publish/%s/edit/%d/saved/%s',
                            SYMPHONY_URL,
                            $this->_context['section_handle'],
                            $entry->get('id'),
                            (!empty($prepopulate_querystring) ? "?" . $prepopulate_querystring : null)
                        )
                    );
                }
            }
        } elseif (@array_key_exists('delete', $_POST['action']) && is_numeric($entry_id)) {
            /**
             * Prior to deletion of entries. An array of Entry ID's is provided which
             * can be manipulated. This delegate was renamed from `Delete` to `EntryPreDelete`
             * in Symphony 2.3.
             *
             * @delegate EntryPreDelete
             * @param string $context
             * '/publish/'
             * @param array $entry_id
             *  An array of Entry ID's passed by reference
             */
            $checked = array($entry_id);
            Symphony::get('ExtensionManager')->notifyMembers('EntryPreDelete', '/publish/', array('entry_id' => &$checked));

            EntryManager::delete($checked);

            /**
             * After the deletion of entries, this delegate provides an array of Entry ID's
             * that were deleted.
             *
             * @since Symphony 2.3
             * @delegate EntryPostDelete
             * @param string $context
             * '/publish/'
             * @param array $entry_id
             *  An array of Entry ID's that were deleted.
             */
            Symphony::get('ExtensionManager')->notifyMembers('EntryPostDelete', '/publish/', array('entry_id' => $checked));

            redirect(SYMPHONY_URL . '/publish/'.$this->_context['section_handle'].'/');
        }
    }

    /**
     * Given a Field and Entry object, this function will wrap
     * the Field's displayPublishPanel result with a div that
     * contains some contextual information such as the Field ID,
     * the Field handle and whether it is required or not.
     *
     * @param Field $field
     * @param Entry $entry
     * @return XMLElement
     */
    private function wrapFieldWithDiv(Field $field, Entry $entry)
    {
        $is_hidden = $this->isFieldHidden($field);
        $div = new XMLElement('div', null, array('id' => 'field-' . $field->get('id'), 'class' => 'field field-'.$field->handle().($field->get('required') == 'yes' ? ' required' : '').($is_hidden == true ? ' irrelevant' : '')));
        $field->displayPublishPanel(
            $div,
            $entry->getData($field->get('id')),
            (isset($this->_errors[$field->get('id')]) ? $this->_errors[$field->get('id')] : null),
            null,
            null,
            (is_numeric($entry->get('id')) ? $entry->get('id') : null)
        );
        return $div;
    }

    /**
     * Check whether a field is a Select Box Link and is hidden
     *
     * @param  Field  $field
     * @return String
     */
    public function isFieldHidden(Field $field)
    {
        if ($field->get('hide_when_prepopulated') == 'yes') {
            if (isset($_REQUEST['prepopulate'])) {
                foreach ($_REQUEST['prepopulate'] as $field_id => $value) {
                    if ($field_id == $field->get('id')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Prepare a Drawer to visualize section associations
     *
     * @param  Section $section The current Section object
     */
    private function prepareAssociationsDrawer($section)
    {
        $entry_id = (!is_null($this->_context['entry_id'])) ? $this->_context['entry_id'] : null;
        $show_entries = Symphony::get('Configuration')->get('association_maximum_rows', 'symphony');

        if (is_null($entry_id) && !isset($_GET['prepopulate']) || is_null($show_entries) || $show_entries == 0) {
            return;
        }

        $parent_associations = SectionManager::fetchParentAssociations($section->get('id'), true);
        $child_associations = SectionManager::fetchChildAssociations($section->get('id'), true);
        $content = null;
        $drawer_position = 'vertical-right';

        /**
         * Prepare Associations Drawer from an Extension
         *
         * @since Symphony 2.3.3
         * @delegate PrepareAssociationsDrawer
         * @param string $context
         * '/publish/'
         * @param integer $entry_id
         *  The entry ID or null
         * @param array $parent_associations
         *  Array of Sections
         * @param array $child_associations
         *  Array of Sections
         * @param string $drawer_position
         *  The position of the Drawer, defaults to `vertical-right`. Available
         *  values of `vertical-left, `vertical-right` and `horizontal`
         */
        Symphony::get('ExtensionManager')->notifyMembers(
            'PrepareAssociationsDrawer',
            '/publish/',
            array(
                'entry_id' => $entry_id,
                'parent_associations' => &$parent_associations,
                'child_associations' => &$child_associations,
                'content' => &$content,
                'drawer-position' => &$drawer_position
            )
        );

        // If there are no associations, return now.
        if ((is_null($parent_associations) || empty($parent_associations))
            &&
            (is_null($child_associations) || empty($child_associations))
        ) {
            return;
        }

        if (!($content instanceof XMLElement)) {
            $content = new XMLElement('div', null, array('class' => 'content'));
            $content->setSelfClosingTag(false);

            // Process Parent Associations
            if (!is_null($parent_associations) && !empty($parent_associations)) {
                foreach ($parent_associations as $as) {
                    if ($field = FieldManager::fetch($as['parent_section_field_id'])) {
                        if (isset($_GET['prepopulate'])) {
                            $prepopulate_field = key($_GET['prepopulate']);
                        }

                        // get associated entries if entry exists,
                        if ($entry_id) {
                            $entry_ids = $this->findParentRelatedEntries($as['child_section_field_id'], $entry_id);
                        } elseif (isset($_GET['prepopulate'])) {
                            // get prepopulated entry otherwise
                            $entry_ids = array(intval(current($_GET['prepopulate'])));
                        } else {
                            $entry_ids = array();
                        }

                        // Use $schema for perf reasons
                        $schema = array($field->get('element_name'));
                        $where = (!empty($entry_ids)) ? sprintf(' AND `e`.`id` IN (%s)', implode(', ', $entry_ids)) : null;
                        $entries = (!empty($entry_ids) || isset($_GET['prepopulate']) && $field->get('id') === $prepopulate_field)
                            ? EntryManager::fetchByPage(1, $as['parent_section_id'], $show_entries, $where, null, false, false, true, $schema)
                            : array();
                        $has_entries = !empty($entries) && $entries['total-entries'] != 0;

                        if($has_entries) {
                            $element = new XMLElement('section', null, array('class' => 'association parent'));
                            $header = new XMLElement('header');
                            $header->appendChild(new XMLElement('p', tr('Linked to %s in', array('<a class="association-section" href="' . SYMPHONY_URL . '/publish/' . $as['handle'] . '/">' . $as['name'] . '</a>'))));
                            $element->appendChild($header);

                            $ul = new XMLElement('ul', null, array(
                                'class' => 'association-links',
                                'data-section-id' => $as['child_section_id'],
                                'data-association-ids' => implode(', ', $entry_ids)
                            ));

                            foreach($entries['records'] as $e) {
                                $value = $field->prepareTableValue($e->getData($field->get('id')), null, $e->get('id'));
                                $li = new XMLElement('li');
                                $a = new XMLElement('a', strip_tags($value));
                                $a->setAttribute('href', SYMPHONY_URL . '/publish/' . $as['handle'] . '/edit/' . $e->get('id') . '/');
                                $li->appendChild($a);
                                $ul->appendChild($li);
                            }

                            $element->appendChild($ul);
                            $content->appendChild($element);
                        }
                    }
                }
            }

            // Process Child Associations
            if (!is_null($child_associations) && !empty($child_associations)) {
                foreach ($child_associations as $as) {
                    // Get the related section
                    $child_section = SectionManager::fetch($as['child_section_id']);
                    if (!($child_section instanceof Section)) {
                        continue;
                    }

                    // Get the visible field instance (using the sorting field, this is more flexible than visibleColumns())
                    // Get the link field instance
                    $visible_field   = current($child_section->fetchVisibleColumns());
                    $relation_field  = FieldManager::fetch($as['child_section_field_id']);

                    // Get entries, using $schema for performance reasons.
                    $entry_ids = $this->findRelatedEntries($as['child_section_field_id'], $entry_id);
                    $schema = $visible_field ? array($visible_field->get('element_name')) : array();
                    $where = sprintf(' AND `e`.`id` IN (%s)', implode(', ', $entry_ids));

                    $entries = (!empty($entry_ids))
                        ? EntryManager::fetchByPage(1, $as['child_section_id'], $show_entries, $where, null, false, false, true, $schema)
                        : array();
                    $has_entries = !empty($entries) && $entries['total-entries'] != 0;

                    // Build the HTML of the relationship
                    $element = new XMLElement('section', null, array('class' => 'association child'));
                    $header = new XMLElement('header');
                    $filter = '?filter[' . $relation_field->get('element_name') . ']=' . $entry_id;
                    $prepopulate = '?prepopulate[' . $as['child_section_field_id'] . ']=' . $entry_id;

                    // Create link with filter or prepopulate
                    $link = SYMPHONY_URL . '/publish/' . $as['handle'] . '/' . $filter;
                    $a = new XMLElement('a', $as['name'], array(
                        'class' => 'association-section',
                        'href' => $link
                    ));

                    // Create new entries
                    $create = new XMLElement('a', tr('Create New'), array(
                        'class' => 'button association-new',
                        'href' => SYMPHONY_URL . '/publish/' . $as['handle'] . '/new/' . $prepopulate
                    ));

                    // Display existing entries
                    if ($has_entries) {
                        $header->appendChild(new XMLElement('p', tr('Links in %s', array($a->generate()))));

                        $ul = new XMLElement('ul', null, array(
                            'class' => 'association-links',
                            'data-section-id' => $as['child_section_id'],
                            'data-association-ids' => implode(', ', $entry_ids)
                        ));

                        foreach ($entries['records'] as $key => $e) {
                            $value = $visible_field ?
                                     $visible_field->prepareTableValue($e->getData($visible_field->get('id')), null, $e->get('id')) :
                                     $e->get('id');
                            $li = new XMLElement('li');
                            $a = new XMLElement('a', strip_tags($value));
                            $a->setAttribute('href', SYMPHONY_URL . '/publish/' . $as['handle'] . '/edit/' . $e->get('id') . '/' . $prepopulate);
                            $li->appendChild($a);
                            $ul->appendChild($li);
                        }

                        $element->appendChild($ul);

                        // If we are only showing 'some' of the entries, then show this on the UI
                        if ($entries['total-entries'] > $show_entries) {
                            $total_entries = new XMLElement('a', tr('%d entries', array($entries['total-entries'])), array(
                                'href' => $link,
                            ));
                            $pagination = new XMLElement('li', null, array(
                                'class' => 'association-more',
                                'data-current-page' => '1',
                                'data-total-pages' => ceil($entries['total-entries'] / $show_entries)
                            ));
                            $counts = new XMLElement('a', tr('Show more entries'), array(
                                'href' => $link
                            ));

                            $pagination->appendChild($counts);
                            $ul->appendChild($pagination);
                        }
                    } else {
                        // No entries
                        $element->setAttribute('class', 'association child empty');
                        $header->appendChild(new XMLElement('p', tr('No links in %s', array($a->generate()))));
                    }

                    $header->appendChild($create);
                    $element->prependChild($header);
                    $content->appendChild($element);
                }
            }
        }

        $drawer = Widget::Drawer('section-associations', tr('Show Associations'), $content);
        $this->insertDrawer($drawer, $drawer_position, 'prepend');
    }

    /**
     * Find related entries from a linking field's data table. Requires the
     * column names to be `entry_id` and `relation_id` as with the Select Box Link
     * @param  integer $field_id
     * @param  integer $entry_id
     * @return array
     */
    public function findRelatedEntries($field_id = null, $entry_id)
    {
        try {
            $ids = Symphony::get('Database')->fetchCol(
                'entry_id',
                sprintf(
                    "SELECT `entry_id`
                    FROM `tbl_entries_data_%d`
                    WHERE `relation_id` = %d
                    AND `entry_id` IS NOT null",
                    $field_id,
                    $entry_id
                )
            );
        } catch (Exception $e) {
            return array();
        }

        return $ids;
    }

    /**
     * Find related entries for the current field. Requires the column names
     * to be `entry_id` and `relation_id` as with the Select Box Link
     * @param  integer $field_id
     * @param  integer $entry_id
     * @return array
     */
    public function findParentRelatedEntries($field_id = null, $entry_id)
    {
        try {
            $ids = Symphony::get('Database')->fetchCol(
                'relation_id',
                sprintf(
                    "SELECT `relation_id`
                    FROM `tbl_entries_data_%d`
                    WHERE `entry_id` = %d
                    AND `relation_id` IS NOT null",
                    $field_id,
                    $entry_id
                )
            );
        } catch (Exception $e) {
            return array();
        }

        return $ids;
    }
}
