<?php

namespace SymphonyCms\Datasources;

use \SymphonyCms\Exceptions\FrontendPageNotFoundException;
use \SymphonyCms\Exceptions\FrontendPageNotFoundExceptionHandler;
use \SymphonyCms\Toolkit\Datasource;
use \SymphonyCms\Datasources\<!-- CLASS EXTENDS -->;
use \SymphonyCms\Toolkit\XMLElement;

class <!-- CLASS NAME -->Datasource extends <!-- CLASS EXTENDS -->
{
    <!-- VAR LIST -->

    <!-- FILTERS -->

    <!-- INCLUDED ELEMENTS -->

    public function __construct($env = null, $process_params = true)
    {
        parent::__construct($env, $process_params);

        $this->_dependencies = array(<!-- DS DEPENDENCY LIST -->);
    }

    public function about()
    {
        return array(
            'name' => '<!-- NAME -->',
            'author' => array(
                'name' => '<!-- AUTHOR NAME -->',
                'website' => '<!-- AUTHOR WEBSITE -->',
                'email' => '<!-- AUTHOR EMAIL -->'),
            'version' => '<!-- VERSION -->',
            'release-date' => '<!-- RELEASE DATE -->'
        );
    }

    public function getSource()
    {
        return '<!-- SOURCE -->';
    }

    public function allowEditorToParse()
    {
        return true;
    }

    public function execute(array &$param_pool = null)
    {
        $result = new XMLElement($this->dsParamROOTELEMENT);

        try {
            $result = parent::execute($param_pool);
        } catch (FrontendPageNotFoundException $e) {
            // Work around. This ensures the 404 page is displayed and
            // is not picked up by the default catch() statement below
            FrontendPageNotFoundExceptionHandler::render($e);
        } catch (\Exception $e) {
            $result->appendChild(new XMLElement('error', $e->getMessage() . ' on ' . $e->getLine() . ' of file ' . $e->getFile()));

            return $result;
        }

        if ($this->_force_empty_result) {
            $result = $this->emptyXMLSet();
        }

        return $result;
    }

}
