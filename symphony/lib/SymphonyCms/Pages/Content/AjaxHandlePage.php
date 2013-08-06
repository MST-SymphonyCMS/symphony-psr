<?php

namespace SymphonyCms\Pages\Content;

use \SymphonyCms\Pages\AjaxPage;

/**
 * The AjaxHandle page is used for generating handles on the fly
 * that are used in Symphony's javascript
 */
class AjaxHandlePage extends AjaxPage
{

    public function handleFailedAuthorisation()
    {
        $this->setHttpStatus(self::HTTP_STATUS_UNAUTHORIZED);
        $this->_Result = json_encode(array('status' => tr('You are not authorised to access this page.')));
    }

    public function view()
    {
        $string = $_GET['string'];

        $this->_Result = json_encode(Lang::createHandle($string, 255, '-', true));
    }

    public function generate($page = null)
    {
        header('Content-Type: application/json');
        echo $this->_Result;
        exit;
    }
}

