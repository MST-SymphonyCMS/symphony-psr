<?php

namespace \SymphonyCms\Datasources;

use \SymphonyCms\Toolkit\Datasource;
use \SymphonyCms\Toolkit\XMLElement;
use \SymphonyCms\Toolkit\XSLTProcess;
use \SymphonyCms\Utilities\General;

/**
 * The `StaticXMLDatasource` allows a block of XML to be exposed to the
 * Frontend. It is a limited to providing the XML as is, and does not
 * support output parameters or any filtering.
 *
 * @since Symphony 2.3
 */
class StaticXMLDatasource extends Datasource
{
    public function execute(array &$param_pool = null)
    {
        $result = new XMLElement($this->dsParamROOTELEMENT);
        $this->dsParamSTATIC = stripslashes($this->dsParamSTATIC);

        if (!General::validateXML($this->dsParamSTATIC, $errors, false, new XSLTProcess)) {
            $result->appendChild(
                new XMLElement('error', tr('XML is invalid.'))
            );

            $element = new XMLElement('errors');

            foreach ($errors as $e) {
                if (strlen(trim($e['message'])) == 0) {
                    continue;
                }

                $element->appendChild(new XMLElement('item', General::sanitize($e['message'])));
            }

            $result->appendChild($element);
        } else {
            $result->setValue($this->dsParamSTATIC);
        }

        return $result;
    }
}
