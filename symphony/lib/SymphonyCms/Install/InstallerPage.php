<?php

namespace SymphonyCms\Install;

use \SymphonyCms\Symphony\DateTimeObj;
use \SymphonyCms\Pages\HTMLPage;
use \SymphonyCms\Toolkit\Lang;
use \SymphonyCms\Toolkit\XMLElement;
use \SymphonyCms\Toolkit\Widget;

/**
 * InstallerPage
 *
 * @package SymphonyCms
 * @subpackage Install
 */
class InstallerPage extends HTMLPage
{
    private $_template;

    protected $_params;

    protected $_page_title;

    public function __construct($template, $params = array())
    {
        parent::__construct();

        $this->_template = $template;
        $this->_params = $params;

        $this->_page_title = tr('Install Symphony');
    }

    public function generate($page = null)
    {
        $this->Html->setDTD('<!DOCTYPE html>');
        $this->Html->setAttribute('lang', Lang::get());

        $this->addHeaderToPage('Cache-Control', 'no-cache, must-revalidate, max-age=0');
        $this->addHeaderToPage('Expires', 'Mon, 12 Dec 1982 06:14:00 GMT');
        $this->addHeaderToPage('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
        $this->addHeaderToPage('Pragma', 'no-cache');

        $this->setTitle($this->_page_title);
        $this->addElementToHead(new XMLElement('meta', null, array('charset' => 'UTF-8')), 1);

        $this->addStylesheetToHead(APP_URL . '/assets/css/symphony.css', 'screen', 30);
        $this->addStylesheetToHead(APP_URL . '/assets/css/symphony.grids.css', 'screen', 31);
        $this->addStylesheetToHead(APP_URL . '/assets/css/symphony.forms.css', 'screen', 32);
        $this->addStylesheetToHead(APP_URL . '/assets/css/symphony.frames.css', 'screen', 33);
        $this->addStylesheetToHead(APP_URL . '/assets/css/installer.css', 'screen', 40);

        return parent::generate($page);
    }

    protected function build($version = VERSION, XMLElement $extra = null)
    {
        parent::build();

        $this->Form = Widget::Form(INSTALL_URL, 'post');

        $title = new XMLElement('h1', $this->_page_title);
        $version = new XMLElement('em', tr('Version %s', array($version)));

        $title->appendChild($version);

        if (!is_null($extra)) {
            $title->appendChild($extra);
        }

        $this->Form->appendChild($title);

        if (isset($this->_params['show-languages']) && $this->_params['show-languages']) {
            $languages = new XMLElement('ul');

            foreach (Lang::getAvailableLanguages(false) as $code => $lang) {
                $languages->appendChild(
                    new XMLElement(
                        'li',
                        Widget::Anchor(
                            $lang,
                            '?lang=' . $code
                        ),
                        ($_REQUEST['lang'] == $code || ($_REQUEST['lang'] == null && $code == 'en')) ? array('class' => 'selected') : array()
                    )
                );
            }

            $languages->appendChild(
                new XMLElement(
                    'li',
                    Widget::Anchor(
                        tr('Symphony is also available in other languages'),
                        'http://getsymphony.com/download/extensions/translations/'
                    ),
                    array('class' => 'more')
                )
            );

            $this->Form->appendChild($languages);
        }

        $this->Body->appendChild($this->Form);

        $function = 'view' . str_replace('-', '', ucfirst($this->_template));
        $this->$function();
    }

    protected function viewMissinglog()
    {
        $h2 = new XMLElement('h2', tr('Missing log file'));

        // What folder wasn't writable? The docroot or the logs folder?
        // RE: #1706
        if (is_writeable(DOCROOT) === false) {
            $folder = DOCROOT;
        } elseif (is_writeable(MANIFEST) === false) {
            $folder = MANIFEST;
        } elseif (is_writeable(LOGS) === false) {
            $folder = LOGS;
        }

        $p = new XMLElement('p', tr('Symphony tried to create a log file and failed. Make sure the %s folder is writable.', array('<code>' . $folder . '</code>')));

        $this->Form->appendChild($h2);
        $this->Form->appendChild($p);
    }

    protected function viewRequirements()
    {
        $h2 = new XMLElement('h2', tr('System Requirements'));

        $this->Form->appendChild($h2);

        if (!empty($this->_params['errors'])) {
            $div = new XMLElement('div');
            $this->appendError(array_keys($this->_params['errors']), $div, tr('Symphony needs to meet the following requirements before thing can be taken to the "next level".'));

            $this->Form->appendChild($div);
        }
    }

    protected function viewLanguages()
    {
        $h2 = new XMLElement('h2', tr('Language selection'));
        $p = new XMLElement('p', tr('This installation can speak in different languages, which one are you fluent in?'));

        $this->Form->appendChild($h2);
        $this->Form->appendChild($p);

        $languages = array();

        foreach (Lang::getAvailableLanguages(false) as $code => $lang) {
            $languages[] = array($code, ($code === 'en'), $lang);
        }

        if (count($languages) > 1) {
            $languages[0][1] = false;
            $languages[1][1] = true;
        }

        $this->Form->appendChild(Widget::Select('lang', $languages));

        $Submit = new XMLElement('div', null, array('class' => 'submit'));
        $Submit->appendChild(Widget::Input('action[proceed]', tr('Proceed with installation'), 'submit'));

        $this->Form->appendChild($Submit);
    }

    protected function viewFailure()
    {
        $h2 = new XMLElement('h2', tr('Installation Failure'));
        $p = new XMLElement('p', tr('An error occurred during installation.'));

        $log = file_get_contents(LOGS . '/install');
        $code = new XMLElement('code', $log);

        $this->Form->appendChild($h2);
        $this->Form->appendChild($p);
        $this->Form->appendChild(
            new XMLElement('pre', $code)
        );
    }

    protected function viewSuccess()
    {
        $this->Form->setAttribute('action', SYMPHONY_URL);

        $div = new XMLElement('div');
        $div->appendChild(
            new XMLElement('h2', tr('The floor is yours'))
        );
        $div->appendChild(
            new XMLElement('p', tr('Thanks for taking the quick, yet epic installation journey with us. It\'s now your turn to shine!'))
        );
        $this->Form->appendChild($div);

        $ul = new XMLElement('ul');

        foreach ($this->_params['disabled-extensions'] as $handle) {
            $ul->appendChild(
                new XMLElement('li', '<code>' . $handle . '</code>')
            );
        }

        if ($ul->getNumberOfChildren() !== 0) {
            $this->Form->appendChild(
                new XMLElement(
                    'p',
                    tr('Looks like the following extensions couldn’t be enabled and must be manually installed. It\'s a minor setback in our otherwise prosperous future together.')
                )
            );
            $this->Form->appendChild($ul);
        }

        $this->Form->appendChild(
            new XMLElement(
                'p',
                tr('I think, you and I will achieve great things together. Just one last thing, how about we remove the installer file and really secure the safety of our relationship.')
            )
        );

        $submit = new XMLElement('div', null, array('class' => 'submit'));
        $submit->appendChild(Widget::Input('submit', tr('Ok, now take me to the login page'), 'submit'));

        $this->Form->appendChild($submit);
    }

    protected function viewConfiguration()
    {
        /* -----------------------------------------------
         * Populating fields array
         * -----------------------------------------------
         */
        $fields = isset($_POST['fields']) ? $_POST['fields'] : $this->_params['default-config'];

        /* -----------------------------------------------
         * Welcome
         * -----------------------------------------------
         */
        $div = new XMLElement('div');
        $div->appendChild(
            new XMLElement('h2', tr('Find something sturdy to hold on to because things are about to get awesome.'))
        );
        $div->appendChild(
            new XMLElement('p', tr('Think of this as a pre-game warm up. You know you\'re going to kick-ass, so you\'re savouring every moment before the show. Welcome to the Symphony install page.'))
        );

        $this->Form->appendChild($div);

        if (!empty($this->_params['errors'])) {
            $this->Form->appendChild(
                Widget::Error(new XMLElement('p'), tr('Oops, a minor hurdle on your path to glory! There appears to be something wrong with the details entered below.'))
            );
        }

        /* -----------------------------------------------
         * Environment settings
         * -----------------------------------------------
         */

        $fieldset = new XMLElement('fieldset');
        $div = new XMLElement('div');
        $this->appendError(array('no-write-permission-root', 'no-write-permission-workspace'), $div);

        if ($div->getNumberOfChildren() > 0) {
            $fieldset->appendChild($div);
            $this->Form->appendChild($fieldset);
        }

        /* -----------------------------------------------
         * Website & Locale settings
         * -----------------------------------------------
         */

        $Environment = new XMLElement('fieldset');
        $Environment->appendChild(new XMLElement('legend', tr('Website Preferences')));

        $label = Widget::label(tr('Name'), Widget::Input('fields[general][sitename]', $fields['general']['sitename']));

        $this->appendError(array('general-no-sitename'), $label);
        $Environment->appendChild($label);

        $Fieldset = new XMLElement('fieldset', null, array('class' => 'frame'));
        $Fieldset->appendChild(new XMLElement('legend', tr('Date and Time')));
        $Fieldset->appendChild(new XMLElement('p', tr('Customise how Date and Time values are displayed throughout the Administration interface.')));

        // Timezones
        $options = DateTimeObj::getTimezonesSelectOptions(
            (isset($fields['region']['timezone']) && !empty($fields['region']['timezone']) ? $fields['region']['timezone'] : date_default_timezone_get())
        );
        $Fieldset->appendChild(Widget::label(tr('Region'), Widget::Select('fields[region][timezone]', $options)));

        // Date formats
        $options = DateTimeObj::getDateFormatsSelectOptions($fields['region']['date_format']);
        $Fieldset->appendChild(Widget::Label(tr('Date Format'), Widget::Select('fields[region][date_format]', $options)));

        // Time formats
        $options = DateTimeObj::getTimeFormatsSelectOptions($fields['region']['time_format']);
        $Fieldset->appendChild(Widget::Label(tr('Time Format'), Widget::Select('fields[region][time_format]', $options)));

        $Environment->appendChild($Fieldset);
        $this->Form->appendChild($Environment);

        /* -----------------------------------------------
         * Database settings
         * -----------------------------------------------
         */

        $Database = new XMLElement('fieldset');
        $Database->appendChild(new XMLElement('legend', tr('Database Connection')));
        $Database->appendChild(new XMLElement('p', tr('Please provide Symphony with access to a database.')));

        // Database name
        $label = Widget::label(tr('Database'), Widget::Input('fields[database][db]', $fields['database']['db']), $class);

        $this->appendError(array('database-incorrect-version', 'unknown-database'), $label);
        $Database->appendChild($label);

        // Database credentials
        $Div = new XMLElement('div', null, array('class' => 'two columns'));
        $Div->appendChild(Widget::label(tr('Username'), Widget::Input('fields[database][user]', $fields['database']['user']), 'column'));
        $Div->appendChild(Widget::label(tr('Password'), Widget::Input('fields[database][password]', $fields['database']['password'], 'password'), 'column'));

        $this->appendError(array('database-invalid-credentials'), $Div);
        $Database->appendChild($Div);

        // Advanced configuration
        $Fieldset = new XMLElement('fieldset', null, array('class' => 'frame'));
        $Fieldset->appendChild(new XMLElement('legend', tr('Advanced Configuration')));
        $Fieldset->appendChild(new XMLElement('p', tr('Leave these fields unless you are sure they need to be changed.')));

        // Advanced configuration: Host, Port
        $Div = new XMLElement('div', null, array('class' => 'two columns'));
        $Div->appendChild(Widget::label(tr('Host'), Widget::Input('fields[database][host]', $fields['database']['host']), 'column'));
        $Div->appendChild(Widget::label(tr('Port'), Widget::Input('fields[database][port]', $fields['database']['port']), 'column'));

        $this->appendError(array('no-database-connection'), $Div);
        $Fieldset->appendChild($Div);

        // Advanced configuration: Table Prefix
        $label = Widget::label(tr('Table Prefix'), Widget::Input('fields[database][tbl_prefix]', $fields['database']['tbl_prefix']));

        $this->appendError(array('database-table-clash'), $label);
        $Fieldset->appendChild($label);

        $Database->appendChild($Fieldset);
        $this->Form->appendChild($Database);

        /* -----------------------------------------------
         * Permission settings
         * -----------------------------------------------
         */

        $Permissions = new XMLElement('fieldset');
        $Permissions->appendChild(new XMLElement('legend', tr('Permission Settings')));
        $Permissions->appendChild(new XMLElement('p', tr('Symphony needs permission to read and write both files and directories.')));

        $Div = new XMLElement('div', null, array('class' => 'two columns'));
        $Div->appendChild(Widget::label(tr('Files'), Widget::Input('fields[file][write_mode]', $fields['file']['write_mode']), 'column'));
        $Div->appendChild(Widget::label(tr('Directories'), Widget::Input('fields[directory][write_mode]', $fields['directory']['write_mode']), 'column'));

        $Permissions->appendChild($Div);
        $this->Form->appendChild($Permissions);

        /* -----------------------------------------------
         * User settings
         * -----------------------------------------------
         */

        $User = new XMLElement('fieldset');
        $User->appendChild(new XMLElement('legend', tr('User Information')));
        $User->appendChild(new XMLElement('p', tr('Once installed, you will be able to login to the Symphony admin with these user details.')));

        // Username
        $label = Widget::label(tr('Username'), Widget::Input('fields[user][username]', $fields['user']['username']));

        $this->appendError(array('user-no-username'), $label);
        $User->appendChild($label);

        // Password
        $Div = new XMLElement('div', null, array('class' => 'two columns'));
        $Div->appendChild(Widget::label(tr('Password'), Widget::Input('fields[user][password]', $fields['user']['password'], 'password'), 'column'));
        $Div->appendChild(Widget::label(tr('Confirm Password'), Widget::Input('fields[user][confirm-password]', $fields['user']['confirm-password'], 'password'), 'column'));

        $this->appendError(array('user-no-password', 'user-password-mismatch'), $Div);
        $User->appendChild($Div);

        // Personal information
        $Fieldset = new XMLElement('fieldset', null, array('class' => 'frame'));
        $Fieldset->appendChild(new XMLElement('legend', tr('Personal Information')));
        $Fieldset->appendChild(new XMLElement('p', tr('Please add the following personal details for this user.')));

        // Personal information: First Name, Last Name
        $Div = new XMLElement('div', null, array('class' => 'two columns'));
        $Div->appendChild(Widget::label(tr('First Name'), Widget::Input('fields[user][firstname]', $fields['user']['firstname']), 'column'));
        $Div->appendChild(Widget::label(tr('Last Name'), Widget::Input('fields[user][lastname]', $fields['user']['lastname']), 'column'));

        $this->appendError(array('user-no-name'), $Div);
        $Fieldset->appendChild($Div);

        // Personal information: Email Address
        $label = Widget::label(tr('Email Address'), Widget::Input('fields[user][email]', $fields['user']['email']));

        $this->appendError(array('user-invalid-email'), $label);
        $Fieldset->appendChild($label);

        $User->appendChild($Fieldset);
        $this->Form->appendChild($User);

        /* -----------------------------------------------
         * Submit area
         * -----------------------------------------------
         */

        $this->Form->appendChild(new XMLElement('h2', tr('Install Symphony')));
        $this->Form->appendChild(new XMLElement('p', tr('The installation process goes by really quickly. Make sure to take a deep breath before you press that sweet button.')));

        $Submit = new XMLElement('div', null, array('class' => 'submit'));
        $Submit->appendChild(Widget::Input('lang', Lang::get(), 'hidden'));

        $Submit->appendChild(Widget::Input('action[install]', tr('Install Symphony'), 'submit'));

        $this->Form->appendChild($Submit);
    }

    private function appendError(array $codes, XMLElement &$element, $message = null)
    {
        if (is_null($message)) {
            $message =  tr('The following errors have been reported:');
        }

        foreach ($codes as $i => $c) {
            if (!isset($this->_params['errors'][$c])) {
                unset($codes[$i]);
            }
        }

        if (!empty($codes)) {
            if (count($codes) > 1) {
                $ul = new XMLElement('ul');

                foreach ($codes as $c) {
                    if (isset($this->_params['errors'][$c])) {
                        $ul->appendChild(new XMLElement('li', $this->_params['errors'][$c]['details'], array('class' => $class)));
                    }
                }

                $element = Widget::Error($element, $message);
                $element->appendChild($ul);
            } else {
                $code = array_pop($codes);

                if (isset($this->_params['errors'][$code])) {
                    $element = Widget::Error($element, $this->_params['errors'][$code]['details']);
                }
            }
        }
    }
}
