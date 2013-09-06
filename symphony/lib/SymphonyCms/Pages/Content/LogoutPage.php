<?php

namespace SymphonyCms\Pages\Content;

use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Pages\HTMLPage;

/**
 * The default Logout page will redirect the user
 * to the Homepage of `URL`
 */
class LogoutPage extends HTMLPage
{
    public function build()
    {
        parent::build();
        $this->view();
    }

    public function view()
    {
        Symphony::get('Engine')->logout();
        redirect(URL);
    }

}
