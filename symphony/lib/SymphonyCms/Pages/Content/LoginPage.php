<?php

namespace SymphonyCms\Pages\Content;

use \Exception;
use \SymphonyCms\Symphony;
use \SymphonyCms\Symphony\Administration;
use \SymphonyCms\Symphony\DateTimeObj;
use \SymphonyCms\Cryptography\SHA1;
use \SymphonyCms\Pages\HTMLPage;
use \SymphonyCms\Toolkit\Email;
use \SymphonyCms\Toolkit\Lang;
use \SymphonyCms\Toolkit\Widget;
use \SymphonyCms\Toolkit\XMLElement;
use \SymphonyCms\Utilities\General;

/**
 * The default Symphony login page that is shown to users who attempt
 * to access `SYMPHONY_URL` but are not logged in. This page has logic
 * to allow users to reset their passwords should they forget.
 */
class LoginPage extends HTMLPage
{
    public $failedLoginAttempt = false;

    public function __construct()
    {
        parent::__construct();
        $this->addHeaderToPage('Content-Type', 'text/html; charset=UTF-8');

        $this->Html->setElementStyle('html');
        $this->Html->setDTD('<!DOCTYPE html>');
        $this->Html->setAttribute('lang', Lang::get());
        $this->addElementToHead(new XMLElement('meta', null, array('charset' => 'UTF-8')), 0);
        $this->addElementToHead(new XMLElement('meta', null, array('http-equiv' => 'X-UA-Compatible', 'content' => 'IE=edge,chrome=1')), 1);
        $this->addElementToHead(new XMLElement('meta', null, array('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1')), 2);

        $this->addStylesheetToHead(APP_URL . '/assets/css/symphony.css', 'screen', 30);
        $this->addStylesheetToHead(APP_URL . '/assets/css/symphony.forms.css', 'screen', 31);
        $this->addStylesheetToHead(APP_URL . '/assets/css/symphony.frames.css', 'screen', 32);

        $this->setTitle(tr('%1$s &ndash; %2$s', array(tr('Login'), tr('Symphony'))));

        $this->Body->setAttribute('id', 'login');

        Symphony::Profiler()->sample('Page template created', PROFILE_LAP);
    }

    public function build($context = null)
    {
        if ($context) {
            $this->_context = $context;
        }

        if (isset($_REQUEST['action'])) {
            $this->action();
        }

        $this->view();
    }

    public function view()
    {
        if (isset($this->_context[0]) && in_array(strlen($this->_context[0]), array(6, 8))) {
            if (!$this->loginFromToken($this->_context[0])) {
                if (Administration::instance()->isLoggedIn()) {
                    redirect(SYMPHONY_URL);
                }
            }
        }

        $this->Form = Widget::Form(SYMPHONY_URL . '/login/', 'post');
        $this->Form->setAttribute('class', 'frame');
        $this->Form->appendChild(new XMLElement('h1', tr('Symphony')));

        $fieldset = new XMLElement('fieldset');

        // Display retrieve password UI
        if (isset($this->_context[0]) && $this->_context[0] == 'retrieve-password') {
            $this->Form->setAttribute('action', SYMPHONY_URL.'/login/retrieve-password/');

            if (isset($this->_email_sent) && $this->_email_sent) {
                $fieldset->appendChild(
                    new XMLElement(
                        'p',
                        tr(
                            'An email containing a customised login link has been sent to %s. It will expire in 2 hours.',
                            array(
                                '<code>' . $this->_email_sent_to . '</code>'
                            )
                        )
                    )
                );
                $fieldset->appendChild(new XMLElement('p', Widget::Anchor(tr('Login'), SYMPHONY_URL.'/login/', null)));
                $this->Form->appendChild($fieldset);
            } else {
                $fieldset->appendChild(new XMLElement('p', tr('Enter your email address or username to be sent further instructions for logging in.')));

                $label = Widget::Label(tr('Email Address or Username'));
                $label->appendChild(Widget::Input('email', General::sanitize($_POST['email']), 'text', array('autofocus' => 'autofocus')));
                if (isset($this->_email_sent) && !$this->_email_sent) {
                    $label = Widget::Error($label, tr('Unfortunately no account was found using this information.'));
                }
                $fieldset->appendChild($label);

                $this->Form->appendChild($fieldset);

                $div = new XMLElement('div', null, array('class' => 'actions'));
                $div->appendChild(
                    new XMLElement('button', tr('Send Email'), array('name' => 'action[reset]', 'type' => 'submit'))
                );
                $div->appendChild(
                    Widget::Anchor(tr('Cancel'), SYMPHONY_URL.'/login/', null, 'action-link')
                );
                $this->Form->appendChild($div);
            }
        } else {
            // Normal login

            $fieldset->appendChild(new XMLElement('legend', tr('Login')));

            // Display error message
            if ($this->failedLoginAttempt) {
                $p = new XMLElement('p');
                $p = Widget::Error($p, tr('The login details provided are incorrect.'));
                $fieldset->appendChild($p);
            }

            // Username
            $label = Widget::Label(tr('Username'));
            $username = Widget::Input('username', isset($_POST['username']) ? General::sanitize($_POST['username']) : null);
            if (!$this->failedLoginAttempt) {
                $username->setAttribute('autofocus', 'autofocus');
            }
            $label->appendChild($username);
            if (isset($_POST['action'], $_POST['action']['login']) && empty($_POST['username'])) {
                $username->setAttribute('autofocus', 'autofocus');
                $label = Widget::Error($label, tr('No username was entered.'));
            }
            $fieldset->appendChild($label);

            // Password
            $label = Widget::Label(tr('Password'));
            $password = Widget::Input('password', null, 'password');
            $label->appendChild($password);

            if (isset($_POST['action'], $_POST['action']['login']) && empty($_POST['password'])) {
                $password->setAttribute('autofocus', 'autofocus');
                $label = Widget::Error($label, tr('No password was entered.'));
            } elseif ($this->failedLoginAttempt) {
                $password->setAttribute('autofocus', 'autofocus');
            }

            $fieldset->appendChild($label);
            $this->Form->appendChild($fieldset);

            // Actions
            $div = new XMLElement('div', null, array('class' => 'actions'));
            $div->appendChild(
                new XMLElement('button', tr('Login'), array('name' => 'action[login]', 'type' => 'submit', 'accesskey' => 's'))
            );
            $div->appendChild(
                Widget::Anchor(tr('Retrieve password?'), SYMPHONY_URL.'/login/retrieve-password/', null, 'action-link')
            );
            $this->Form->appendChild($div);

            if (isset($this->_context['redirect'])) {
                $this->Form->appendChild(
                    Widget::Input('redirect', SYMPHONY_URL . General::sanitize($this->_context['redirect']), 'hidden')
                );
            }
        }

        $this->Body->appendChild($this->Form);
    }

    public function action()
    {
        if (isset($_POST['action'])) {
            $actionParts = array_keys($_POST['action']);
            $action = end($actionParts);

            // Login Attempted
            if ($action == 'login') {
                if (empty($_POST['username']) || empty($_POST['password']) || !Administration::instance()->login($_POST['username'], $_POST['password'])) {
                    /**
                     * A failed login attempt into the Symphony backend
                     *
                     * @delegate AuthorLoginFailure
                     * @since Symphony 2.2
                     * @param string $context
                     * '/login/'
                     * @param string $username
                     *  The username of the Author who attempted to login.
                     */
                    Symphony::ExtensionManager()->notifyMembers(
                        'AuthorLoginFailure',
                        '/login/',
                        array(
                            'username' => Symphony::Database()->cleanValue($_POST['username'])
                        )
                    );
                    $this->failedLoginAttempt = true;
                } else {
                    /**
                     * A successful login attempt into the Symphony backend
                     *
                     * @delegate AuthorLoginSuccess
                     * @since Symphony 2.2
                     * @param string $context
                     * '/login/'
                     * @param string $username
                     *  The username of the Author who logged in.
                     */
                    Symphony::ExtensionManager()->notifyMembers(
                        'AuthorLoginSuccess',
                        '/login/',
                        array(
                            'username' => Symphony::Database()->cleanValue($_POST['username'])
                        )
                    );

                    isset($_POST['redirect']) ? redirect($_POST['redirect']) : redirect(SYMPHONY_URL);
                }
            } elseif ($action == 'reset') {
                // Reset of password requested

                $author = Symphony::Database()->fetchRow(0, sprintf("
                        SELECT `id`, `email`, `first_name`
                        FROM `tblauthors`
                        WHERE `email` = '%1\$s' OR `username` = '%1\$s'
                    ", Symphony::Database()->cleanValue($_POST['email'])
                ));

                if (!empty($author)) {
                    Symphony::Database()->delete('tbl_forgotpass', " `expiry` < '".DateTimeObj::getGMT('c')."' ");

                    if (!$token = Symphony::Database()->fetchVar('token', 0, "SELECT `token` FROM `tbl_forgotpass` WHERE `expiry` > '".DateTimeObj::getGMT('c')."' AND `author_id` = ".$author['id'])) {
                        $token = substr(SHA1::hash(time() . rand(0, 1000)), 0, 6);
                        Symphony::Database()->insert(
                            array(
                                'author_id' => $author['id'],
                                'token' => $token,
                                'expiry' => DateTimeObj::getGMT('c', time() + (120 * 60))
                            ),
                            'tbl_forgotpass'
                        );
                    }

                    try {
                        $email = Email::create();

                        $email->recipients = $author['email'];
                        $email->subject = tr('New Symphony Account Password');
                        $email->text_plain = tr('Hi %s,', array($author['first_name'])) . PHP_EOL .
                                tr('A new password has been requested for your account. Login using the following link, and change your password via the Authors area:') . PHP_EOL .
                                PHP_EOL . ' ' . SYMPHONY_URL . "/login/{$token}/" . PHP_EOL . PHP_EOL .
                                tr('It will expire in 2 hours. If you did not ask for a new password, please disregard this email.') . PHP_EOL . PHP_EOL .
                                tr('Best Regards,') . PHP_EOL .
                                tr('The Symphony Team');

                        $email->send();
                        $this->_email_sent = true;
                        $this->_email_sent_to = $author['email']; // Set this so we can display a customised message
                    } catch (Exception $e) {

                    }

                    /**
                     * When a password reset has occurred and after the Password
                     * Reset email has been sent.
                     *
                     * @delegate AuthorPostPasswordResetSuccess
                     * @since Symphony 2.2
                     * @param string $context
                     * '/login/'
                     * @param integer $author_id
                     *  The ID of the Author who requested the password reset
                     */
                    Symphony::ExtensionManager()->notifyMembers(
                        'AuthorPostPasswordResetSuccess',
                        '/login/',
                        array(
                            'author_id' => $author['id']
                        )
                    );
                } else {
                    /**
                     * When a password reset has been attempted, but Symphony doesn't
                     * recognise the credentials the user has given.
                     *
                     * @delegate AuthorPostPasswordResetFailure
                     * @since Symphony 2.2
                     * @param string $context
                     * '/login/'
                     * @param string $email
                     *  The sanitised Email of the Author who tried to request the password reset
                     */
                    Symphony::ExtensionManager()->notifyMembers(
                        'AuthorPostPasswordResetFailure',
                        '/login/',
                        array(
                            'email' => Symphony::Database()->cleanValue($_POST['email'])
                        )
                    );

                    $this->_email_sent = false;
                }
            }
        }
    }

    public function loginFromToken($token)
    {
        // If token is invalid, return to login page
        if (!Administration::instance()->loginFromToken($token)) {
            return false;
        }

        // If token is valid and is an 8 char shortcut
        if (strlen($token) != 6) {
            redirect(SYMPHONY_URL); // Regular token-based login
        }

        return false;
    }
}
