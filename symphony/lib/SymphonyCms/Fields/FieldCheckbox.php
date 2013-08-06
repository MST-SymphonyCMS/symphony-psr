<?php

namespace SymphonyCms\Fields;

use \SymphonyCms\Symphony;
use \SymphonyCms\Fields\FieldCheckbox;
use \SymphonyCms\Interfaces\ExportableFieldInterface;
use \SymphonyCms\Interfaces\ImportableFieldInterface;
use \SymphonyCms\Toolkit\Field;
use \SymphonyCms\Toolkit\FieldManager;
use \SymphonyCms\Toolkit\XMLElement;
use \SymphonyCms\Toolkit\Widget;

/**
 * Checkbox field simulates a HTML checkbox field, in that it represents a
 * simple yes/no field.
 */
class FieldCheckbox extends Field implements ExportableFieldInterface, ImportableFieldInterface
{
    public function __construct()
    {
        parent::__construct();

        $this->_name = tr('Checkbox');
        $this->_required = true;

        $this->set('required', 'no');
        $this->set('location', 'sidebar');
    }
    /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

    public function canToggle()
    {
        return true;
    }

    public function getToggleStates()
    {
        return array(
            'yes' => tr('Yes'),
            'no' => tr('No')
        );
    }

    public function toggleFieldData(array $data, $newState, $entry_id = null)
    {
        $data['value'] = $newState;
        return $data;
    }

    public function canFilter()
    {
        return true;
    }

    public function isSortable()
    {
        return true;
    }

    public function allowDatasourceOutputGrouping()
    {
        return true;
    }

    public function allowDatasourceParamOutput()
    {
        return true;
    }

    /*-------------------------------------------------------------------------
        Setup:
    -------------------------------------------------------------------------*/

    public function createTable()
    {
        return Symphony::Database()->query(
            "CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
              `id` int(11) unsigned NOT null auto_increment,
              `entry_id` int(11) unsigned NOT null,
              `value` enum('yes','no') NOT null default '".($this->get('default_state') == 'on' ? 'yes' : 'no')."',
              PRIMARY KEY  (`id`),
              UNIQUE KEY `entry_id` (`entry_id`),
              KEY `value` (`value`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
        );
    }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

    public function findDefaults(array &$settings)
    {
        if (!isset($settings['default_state'])) {
            $settings['default_state'] = 'off';
        }
    }

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        $div = new XMLElement('div', null, array('class' => 'two columns'));
        $this->appendRequiredCheckbox($div);
        $this->appendShowColumnCheckbox($div);
        $wrapper->appendChild($div);

        // Checkbox Default State
        $div = new XMLElement('div', null, array('class' => 'two columns'));
        $label = Widget::Label();
        $label->setAttribute('class', 'column');
        $input = Widget::Input('fields['.$this->get('sortorder').'][default_state]', 'on', 'checkbox');

        if ($this->get('default_state') == 'on') {
            $input->setAttribute('checked', 'checked');
        }

        $label->setValue(tr('%s Checked by default', array($input->generate())));
        $div->appendChild($label);
        $wrapper->appendChild($div);
    }

    public function commit()
    {
        if (!parent::commit()) {
            return false;
        }

        $id = $this->get('id');

        if ($id === false) {
            return false;
        }

        $fields = array();

        $fields['default_state'] = ($this->get('default_state') ? $this->get('default_state') : 'off');

        return FieldManager::saveSettings($id, $fields);
    }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
    {
        if (!$data) {
            // TODO: Don't rely on $_POST
            if (isset($_POST) && !empty($_POST)) {
                $value = 'no';
            } elseif ($this->get('default_state') == 'on') {
                $value = 'yes';
            } else {
                $value = 'no';
            }
        } else {
            $value = ($data['value'] == 'yes' ? 'yes' : 'no');
        }

        $label = Widget::Label();

        if ($this->get('required') != 'yes') {
            $label->appendChild(new XMLElement('i', tr('Optional')));
        }

        $input = Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, 'yes', 'checkbox', ($value == 'yes' ? array('checked' => 'checked') : null));

        $label->setValue($input->generate(false) . ' ' . $this->get('label'));

        if ($flagWithError != null) {
            $wrapper->appendChild(Widget::Error($label, $flagWithError));
        } else {
            $wrapper->appendChild($label);
        }
    }

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $message = null;

        // Check if any value was passed
        $has_no_value = is_array($data) ? empty($data) : strlen(trim($data)) == 0;
        // Check that the value passed was 'on' or 'yes', if it's not
        // then the field has 'no value' in the context of being required. RE: #1569
        $has_no_value = ($has_no_value === false) ? !in_array(strtolower($data), array('on', 'yes')) : true;

        if ($this->get('required') == 'yes' && $has_no_value) {
            $message = tr('‘%s’ is a required field.', array($this->get('label')));

            return self::__MISSING_FIELDS__;
        }

        return self::__OK__;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $status = self::__OK__;

        return array(
            'value' => (strtolower($data) == 'yes' || strtolower($data) == 'on' ? 'yes' : 'no')
        );
    }

    /*-------------------------------------------------------------------------
        Output:
    -------------------------------------------------------------------------*/

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        $value = ($data['value'] == 'yes' ? 'Yes' : 'No');

        $wrapper->appendChild(new XMLElement($this->get('element_name'), ($encode ? General::sanitize($value) : $value)));
    }

    public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
    {
        return $this->prepareExportValue($data, ExportableFieldInterface::VALUE, $entry_id);
    }

    public function getParameterPoolValue(array $data, $entry_id = null)
    {
        return $this->prepareExportValue($data, ExportableFieldInterface::POSTDATA, $entry_id);
    }

    /*-------------------------------------------------------------------------
        Import:
    -------------------------------------------------------------------------*/

    public function getImportModes()
    {
        return array(
            'getValue' =>       ImportableFieldInterface::STRING_VALUE,
            'getPostdata' =>    ImportableFieldInterface::ARRAY_VALUE
        );
    }

    public function prepareImportValue($data, $mode, $entry_id = null)
    {
        $value = $status = $message = null;
        $modes = (object)$this->getImportModes();

        $value = $this->processRawFieldData($data, $status, $message, true, $entry_id);

        if ($mode === $modes->getValue) {
            return $value['value'];
        } elseif ($mode === $modes->getPostdata) {
            return $value;
        }

        return null;
    }

    /*-------------------------------------------------------------------------
        Export:
    -------------------------------------------------------------------------*/

    /**
     * Return a list of supported export modes for use with `prepareExportValue`.
     *
     * @return array
     */
    public function getExportModes()
    {
        return array(
            'getBoolean' =>     ExportableFieldInterface::BOOLEAN,
            'getValue' =>       ExportableFieldInterface::VALUE,
            'getPostdata' =>    ExportableFieldInterface::POSTDATA
        );
    }

    /**
     * Give the field some data and ask it to return a value using one of many
     * possible modes.
     *
     * @param mixed $data
     * @param integer $mode
     * @param integer $entry_id
     * @return string|boolean|null
     */
    public function prepareExportValue($data, $mode, $entry_id = null)
    {
        $modes = (object)$this->getExportModes();

        // Export unformatted:
        if ($mode === $modes->getPostdata) {
            return (
                isset($data['value'])
                && $data['value'] == 'yes'
                    ? 'yes'
                    : 'no'
            );
        } elseif ($mode === $modes->getValue) {
            // Export formatted:
            return (
                isset($data['value'])
                && $data['value'] == 'yes'
                    ? tr('Yes')
                    : tr('No')
            );
        } elseif ($mode === $modes->getBoolean) {
            // Export boolean:
            return (
                isset($data['value'])
                && $data['value'] == 'yes'
            );
        }

        return null;
    }

    /*-------------------------------------------------------------------------
        Filtering:
    -------------------------------------------------------------------------*/

    public function displayDatasourceFilterPanel(XMLElement &$wrapper, $data = null, $errors = null, $fieldnamePrefix = null, $fieldnamePostfix = null)
    {
        parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);

        $existing_options = array('yes', 'no');

        if (is_array($existing_options) && !empty($existing_options)) {
            $optionlist = new XMLElement('ul');
            $optionlist->setAttribute('class', 'tags');

            foreach ($existing_options as $option) {
                $optionlist->appendChild(new XMLElement('li', $option));
            }

            $wrapper->appendChild($optionlist);
        }
    }

    public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false)
    {
        $field_id = $this->get('id');
        $default_state = ($this->get('default_state') == "on") ? 'yes' : 'no';

        if ($andOperation) {
            foreach ($data as $value) {
                $this->_key++;
                $value = $this->cleanValue($value);
                $joins .= "
                    LEFT JOIN
                        `tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
                        ON (e.id = t{$field_id}_{$this->_key}.entry_id)
                ";

                if ($default_state == $value) {
                    $where .= "
                        AND (
                            t{$field_id}_{$this->_key}.value = '{$value}'
                            OR
                            t{$field_id}_{$this->_key}.value IS null
                        )
                    ";
                } else {
                    $where .= "
                        AND (t{$field_id}_{$this->_key}.value = '{$value}')
                    ";
                }
            }
        } else {
            if (!is_array($data)) {
                $data = array($data);
            }

            foreach ($data as &$value) {
                $value = $this->cleanValue($value);
            }

            $this->_key++;
            $data = implode("', '", $data);
            $joins .= "
                LEFT JOIN
                    `tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
                    ON (e.id = t{$field_id}_{$this->_key}.entry_id)
            ";

            if (strpos($data, $default_state) !== false) {
                $where .= "
                    AND (
                        t{$field_id}_{$this->_key}.value IN ('{$data}')
                        OR
                        t{$field_id}_{$this->_key}.value IS null
                    )
                ";
            } else {
                $where .= "
                    AND (t{$field_id}_{$this->_key}.value IN ('{$data}'))
                ";
            }
        }

        return true;
    }

    /*-------------------------------------------------------------------------
        Sorting:
    -------------------------------------------------------------------------*/

    public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC')
    {
        if (in_array(strtolower($order), array('random', 'rand'))) {
            $sort = 'ORDER BY RAND()';
        } else {
            $sort = sprintf(
                'ORDER BY (
                    SELECT %s
                    FROM tbl_entries_data_%d AS `ed`
                    WHERE entry_id = e.id
                ) %s',
                '`ed`.value',
                $this->get('id'),
                $order
            );
        }
    }

    /*-------------------------------------------------------------------------
        Grouping:
    -------------------------------------------------------------------------*/

    public function groupRecords($records)
    {
        if (!is_array($records) || empty($records)) {
            return;
        }

        $groups = array($this->get('element_name') => array());

        foreach ($records as $r) {
            $data = $r->getData($this->get('id'));

            $value = $data['value'];

            if (!isset($groups[$this->get('element_name')][$value])) {
                $groups[$this->get('element_name')][$value] = array(
                    'attr' => array('value' => $value),
                    'records' => array(),
                    'groups' => array()
                );
            }

            $groups[$this->get('element_name')][$value]['records'][] = $r;
        }

        return $groups;
    }

    /*-------------------------------------------------------------------------
        Events:
    -------------------------------------------------------------------------*/

    public function getExampleFormMarkup()
    {
        $label = Widget::Label($this->get('label'));
        $label->appendChild(Widget::Input('fields['.$this->get('element_name').']', 'yes', 'checkbox', ($this->get('default_state') == 'on' ? array('checked' => 'checked') : null)));

        return $label;
    }
}