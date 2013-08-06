<?php

namespace SymphonyCms\Fields;

use \SymphonyCms\Symphony;
use \SymphonyCms\Interfaces\ExportableFieldInterface;
use \SymphonyCms\Interfaces\ImportableFieldInterface;
use \SymphonyCms\Toolkit\Field;
use \SymphonyCms\Toolkit\FieldManager;
use \SymphonyCms\Toolkit\TextformatterManager;
use \SymphonyCms\Toolkit\XMLElement;
use \SymphonyCms\Toolkit\XSLTProcess;
use \SymphonyCms\Toolkit\Widget;
use \SymphonyCms\Utilities\General;

/**
 * A simple Textarea field that essentially maps to HTML's `<textarea/>`.
 */
class FieldTextarea extends Field implements ExportableFieldInterface, ImportableFieldInterface
{
    public function __construct()
    {
        parent::__construct();

        $this->_name = tr('Textarea');
        $this->_required = true;

        // Set default
        $this->set('show_column', 'no');
        $this->set('required', 'no');
    }

    /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

    public function canFilter()
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
              `value` MEDIUMTEXT,
              `value_formatted` MEDIUMTEXT,
              PRIMARY KEY  (`id`),
              UNIQUE KEY `entry_id` (`entry_id`),
              FULLTEXT KEY `value` (`value`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
        );
    }

    /*-------------------------------------------------------------------------
        Utilities:
    -------------------------------------------------------------------------*/

    protected function applyFormatting($data, $validate = false, &$errors = null)
    {
        $result = '';

        if ($this->get('formatter')) {
            $formatter = TextformatterManager::create($this->get('formatter'));
            $result = $formatter->run($data);
        }

        if ($validate === true) {
            if (!General::validateXML($result, $errors, false, new XSLTProcess)) {
                $result = html_entity_decode($result, ENT_QUOTES, 'UTF-8');
                $result = $this->replaceAmpersands($result);

                if (!General::validateXML($result, $errors, false, new XSLTProcess)) {
                    return false;
                }
            }
        }

        return $result;
    }

    private function replaceAmpersands($value)
    {
        return preg_replace('/&(?!(#[0-9]+|#x[0-9a-f]+|amp|lt|gt);)/i', '&amp;', trim($value));
    }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

    public function findDefaults(array &$settings)
    {
        if (!isset($settings['size'])) {
            $settings['size'] = 15;
        }
    }

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        // Textarea Size
        $label = Widget::Label(tr('Number of default rows'));
        $label->setAttribute('class', 'column');
        $input = Widget::Input('fields['.$this->get('sortorder').'][size]', (string)$this->get('size'));
        $label->appendChild($input);

        $div = new XMLElement('div');
        $div->setAttribute('class', 'two columns');
        $div->appendChild($this->buildFormatterSelect($this->get('formatter'), 'fields['.$this->get('sortorder').'][formatter]', tr('Text Formatter')));
        $div->appendChild($label);
        $wrapper->appendChild($div);

        $div =  new XMLElement('div', null, array('class' => 'two columns'));
        $this->appendRequiredCheckbox($div);
        $this->appendShowColumnCheckbox($div);
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

        if ($this->get('formatter') != 'none') {
            $fields['formatter'] = $this->get('formatter');
        }

        $fields['size'] = $this->get('size');

        return FieldManager::saveSettings($id, $fields);
    }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
    {
        $label = Widget::Label($this->get('label'));

        if ($this->get('required') != 'yes') {
            $label->appendChild(new XMLElement('i', tr('Optional')));
        }

        $value = isset($data['value']) ? $data['value'] : null;
        $textarea = Widget::Textarea('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, (int)$this->get('size'), 50, (strlen($value) != 0 ? General::sanitize($value) : null));

        if ($this->get('formatter') != 'none') {
            $textarea->setAttribute('class', $this->get('formatter'));
        }

        /**
         * Allows developers modify the textarea before it is rendered in the publish forms
         *
         * @delegate ModifyTextareaFieldPublishWidget
         * @param string $context
         * '/backend/'
         * @param Field $field
         * @param Widget $label
         * @param Widget $textarea
         */
        Symphony::ExtensionManager()->notifyMembers(
            'ModifyTextareaFieldPublishWidget',
            '/backend/',
            array(
                'field' => &$this,
                'label' => &$label,
                'textarea' => &$textarea
            )
        );

        $label->appendChild($textarea);

        if ($flagWithError != null) {
            $wrapper->appendChild(Widget::Error($label, $flagWithError));
        } else {
            $wrapper->appendChild($label);
        }
    }

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $message = null;

        if ($this->get('required') == 'yes' && strlen($data) == 0) {
            $message = tr('‘%s’ is a required field.', array($this->get('label')));
            return self::__MISSING_FIELDS__;
        }

        if ($this->applyFormatting($data, true, $errors) === false) {
            $message = tr('‘%s’ contains invalid XML.', array($this->get('label'))) . ' ' . tr('The following error was returned:') . ' <code>' . $errors[0]['message'] . '</code>';
            return self::__INVALID_FIELDS__;
        }

        return self::__OK__;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $status = self::__OK__;

        $result = array(
            'value' => $data
        );

        $result['value_formatted'] = $this->applyFormatting($data, true, $errors);

        if ($result['value_formatted'] === false) {
            // Run the formatter again, but this time do not validate. We will sanitize the output
            $result['value_formatted'] = General::sanitize($this->applyFormatting($data));
        }

        return $result;
    }

    /*-------------------------------------------------------------------------
        Output:
    -------------------------------------------------------------------------*/

    public function fetchIncludableElements()
    {
        if ($this->get('formatter')) {
            return array(
                $this->get('element_name') . ': formatted',
                $this->get('element_name') . ': unformatted'
            );
        }

        return array(
            $this->get('element_name')
        );
    }

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        $attributes = array();

        if (!is_null($mode)) {
            $attributes['mode'] = $mode;
        }

        if ($mode == 'formatted') {
            if ($this->get('formatter') && isset($data['value_formatted'])) {
                $value = $data['value_formatted'];
            } else {
                $value = $this->replaceAmpersands($data['value']);
            }

            $wrapper->appendChild(
                new XMLElement(
                    $this->get('element_name'),
                    ($encode ? General::sanitize($value) : $value),
                    $attributes
                )
            );
        } elseif ($mode == null || $mode == 'unformatted') {
            $wrapper->appendChild(
                new XMLElement(
                    $this->get('element_name'),
                    sprintf('<![CDATA[%s]]>', str_replace(']]>',']]]]><![CDATA[>',$data['value'])),
                    $attributes
                )
            );
        }
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
        $message = $status = null;
        $modes = (object)$this->getImportModes();

        if ($mode === $modes->getValue) {
            return $data;
        } elseif ($mode === $modes->getPostdata) {
            return $this->processRawFieldData($data, $status, $message, true, $entry_id);
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
            'getHandle' =>      ExportableFieldInterface::HANDLE,
            'getFormatted' =>   ExportableFieldInterface::FORMATTED,
            'getUnformatted' => ExportableFieldInterface::UNFORMATTED,
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
     * @return string|null
     */
    public function prepareExportValue($data, $mode, $entry_id = null)
    {
        $modes = (object)$this->getExportModes();

        // Export handles:
        if ($mode === $modes->getHandle) {
            if (isset($data['handle'])) {
                return $data['handle'];
            } elseif (isset($data['value'])) {
                return General::createHandle($data['value']);
            }
        } elseif ($mode === $modes->getUnformatted || $mode === $modes->getPostdata) {
            // Export unformatted:
            return isset($data['value'])
                ? $data['value']
                : null;
        } elseif ($mode === $modes->getFormatted) {
            // Export formatted:
            if (isset($data['value_formatted'])) {
                return $data['value_formatted'];
            } elseif (isset($data['value'])) {
                return General::sanitize($data['value']);
            }
        }

        return null;
    }

    /*-------------------------------------------------------------------------
        Filtering:
    -------------------------------------------------------------------------*/

    public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false)
    {
        $field_id = $this->get('id');

        if (self::isFilterRegex($data[0])) {
            $this->buildRegexSQL($data[0], array('value'), $joins, $where);
        } else {
            if (is_array($data)) {
                $data = $data[0];
            }

            $this->_key++;
            $data = $this->cleanValue($data);
            $joins .= "
                LEFT JOIN
                    `tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
                    ON (e.id = t{$field_id}_{$this->_key}.entry_id)
            ";
            $where .= "
                AND MATCH (t{$field_id}_{$this->_key}.value) AGAINST ('{$data}' IN BOOLEAN MODE)
            ";
        }

        return true;
    }

    /*-------------------------------------------------------------------------
        Events:
    -------------------------------------------------------------------------*/

    public function getExampleFormMarkup()
    {
        $label = Widget::Label($this->get('label'));
        $label->appendChild(Widget::Textarea('fields['.$this->get('element_name').']', (int)$this->get('size'), 50));

        return $label;
    }
}
