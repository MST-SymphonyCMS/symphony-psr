<?php

namespace SymphonyCms\Pages\Content;

use \SymphonyCms\Symphony;
use \SymphonyCms\Pages\AdministrationPage;
use \SymphonyCms\Toolkit\ExtensionManager;
use \SymphonyCms\Toolkit\Sortable;
use \SymphonyCms\Toolkit\Widget;
use \SymphonyCms\Toolkit\XMLElement;

/**
 * This page generates the Extensions index which shows all Extensions
 * that are available in this Symphony installation.
 */
class SystemExtensionsPage extends AdministrationPage
{
    public function sort(&$sort, &$order, $params)
    {
        if (is_null($sort)) {
            $sort = 'name';
        }

        return ExtensionManager::fetch(array(), array(), $sort . ' ' . $order);
    }

    public function viewIndex()
    {
        $this->setPageType('table');
        $this->setTitle(tr('%1$s &ndash; %2$s', array(tr('Extensions'), tr('Symphony'))));
        $this->appendSubheading(tr('Extensions'));

        $this->Form->setAttribute('action', SYMPHONY_URL . '/system/extensions/');

        Sortable::initialize($this, $extensions, $sort, $order);

        $columns = array(
            array(
                'label' => tr('Name'),
                'sortable' => true,
                'handle' => 'name'
            ),
            array(
                'label' => tr('Installed Version'),
                'sortable' => false,
            ),
            array(
                'label' => tr('Enabled'),
                'sortable' => false,
            ),
            array(
                'label' => tr('Authors'),
                'sortable' => true,
                'handle' => 'author'
            )
        );

        $aTableHead = Sortable::buildTableHeaders(
            $columns,
            $sort,
            $order,
            (isset($_REQUEST['filter']) ? '&amp;filter=' . $_REQUEST['filter'] : '')
        );

        $aTableBody = array();

        if (!is_array($extensions) || empty($extensions)) {
            $aTableBody = array(
                Widget::TableRow(array(Widget::TableData(tr('None found.'), 'inactive', null, count($aTableHead))), 'odd')
            );
        } else {
            foreach ($extensions as $name => $about) {
                $td1 = Widget::TableData($about['name']);
                $installed_version = Symphony::ExtensionManager()->fetchInstalledVersion($name);
                $td2 = Widget::TableData(is_null($installed_version) ? tr('Not Installed') : $installed_version);

                // If the extension is using the new `extension.meta.xml` format, check the
                // compatibility of the extension. This won't prevent a user from installing
                // it, but it will let them know that it requires a version of Symphony greater
                // then what they have.
                if (in_array(EXTENSION_NOT_INSTALLED, $about['status'])) {
                    $td3 = Widget::TableData(tr('Enable to install %s', array($about['version'])));
                }

                if (in_array(EXTENSION_NOT_COMPATIBLE, $about['status'])) {
                    $td3 = Widget::TableData(tr('Requires Symphony %s', array($about['required_version'])));
                }

                if (in_array(EXTENSION_ENABLED, $about['status'])) {
                    $td3 = Widget::TableData(tr('Yes'));
                }

                if (in_array(EXTENSION_REQUIRES_UPDATE, $about['status'])) {
                    if (in_array(EXTENSION_NOT_COMPATIBLE, $about['status'])) {
                        $td3 = Widget::TableData(tr('New version %1$s, Requires Symphony %2$s', array($about['version'], $about['required_version'])));
                    } else {
                        $td3 = Widget::TableData(tr('Enable to update to %s', array($about['version'])));
                    }
                }

                if (in_array(EXTENSION_DISABLED, $about['status'])) {
                    $td3 = Widget::TableData(tr('Disabled'));
                }

                $td4 = Widget::TableData(null);

                if (isset($about['author'][0]) && is_array($about['author'][0])) {
                    $authors = '';
                    foreach ($about['author'] as $i => $author) {
                        if (isset($author['website'])) {
                            $link = Widget::Anchor($author['name'], General::validateURL($author['website']));
                        } elseif (isset($author['email'])) {
                            $link = Widget::Anchor($author['name'], 'mailto:' . $author['email']);
                        } else {
                            $link = $author['name'];
                        }

                        $authors .= ($link instanceof XMLElement ? $link->generate() : $link)
                                . ($i != count($about['author']) - 1 ? ", " : "");
                    }

                    $td4->setValue($authors);
                } else {
                    if (isset($about['author']['website'])) {
                        $link = Widget::Anchor($about['author']['name'], General::validateURL($about['author']['website']));
                    } elseif (isset($about['author']['email'])) {
                        $link = Widget::Anchor($about['author']['name'], 'mailto:' . $about['author']['email']);
                    } else {
                        $link = $about['author']['name'];
                    }

                    $td4->setValue($link instanceof XMLElement ? $link->generate() : $link);
                }

                $td4->appendChild(Widget::Input('items['.$name.']', 'on', 'checkbox'));

                // Add a row to the body array, assigning each cell to the row
                $aTableBody[] = Widget::TableRow(array($td1, $td2, $td3, $td4), (in_array(EXTENSION_NOT_INSTALLED, $about['status']) ? 'inactive' : null));
            }
        }

        $table = Widget::Table(
            Widget::TableHead($aTableHead),
            null,
            Widget::TableBody($aTableBody),
            'selectable'
        );

        $this->Form->appendChild($table);

        $version = new XMLElement(
            'p',
            'Symphony ' . Symphony::Configuration()->get('version', 'symphony'),
            array(
                'id' => 'version'
            )
        );
        $this->Form->appendChild($version);

        $tableActions = new XMLElement('div');
        $tableActions->setAttribute('class', 'actions');

        $options = array(
            array(null, false, tr('With Selected...')),
            array('enable', false, tr('Enable/Install')),
            array('disable', false, tr('Disable')),
            array('uninstall', false, tr('Uninstall'), 'confirm', null, array(
                'data-message' => tr('Are you sure you want to uninstall the selected extensions?')
            ))
        );

        /**
         * Allows an extension to modify the existing options for this page's
         * With Selected menu. If the `$options` parameter is an empty array,
         * the 'With Selected' menu will not be rendered.
         *
         * @delegate AddCustomActions
         * @since Symphony 2.3.2
         * @param string $context
         * '/system/extensions/'
         * @param array $options
         *  An array of arrays, where each child array represents an option
         *  in the With Selected menu. Options should follow the same format
         *  expected by `Widget::selectBuildOption`. Passed by reference.
         */
        Symphony::ExtensionManager()->notifyMembers(
            'AddCustomActions',
            '/system/extensions/',
            array(
                'options' => &$options
            )
        );

        if (!empty($options)) {
            $tableActions->appendChild(Widget::Apply($options));
            $this->Form->appendChild($tableActions);
        }
    }

    public function actionIndex()
    {
        $checked = (is_array($_POST['items'])) ? array_keys($_POST['items']) : null;

        /**
         * Extensions can listen for any custom actions that were added
         * through `AddCustomPreferenceFieldsets` or `AddCustomActions`
         * delegates.
         *
         * @delegate CustomActions
         * @since Symphony 2.3.2
         * @param string $context
         *  '/system/extensions/'
         * @param array $checked
         *  An array of the selected rows. The value is usually the ID of the
         *  the associated object.
         */
        Symphony::ExtensionManager()->notifyMembers(
            'CustomActions',
            '/system/extensions/',
            array(
                'checked' => $checked
            )
        );

        if (isset($_POST['with-selected']) && is_array($checked) && !empty($checked)) {
            try {
                switch ($_POST['with-selected']) {
                    case 'enable':
                        /**
                         * Notifies just before an Extension is to be enabled.
                         *
                         * @delegate ExtensionPreEnable
                         * @since Symphony 2.2
                         * @param string $context
                         * '/system/extensions/'
                         * @param array $extensions
                         *  An array of all the extension name's to be enabled, passed by reference
                         */
                        Symphony::ExtensionManager()->notifyMembers('ExtensionPreEnable', '/system/extensions/', array('extensions' => &$checked));

                        foreach ($checked as $name) {
                            if (Symphony::ExtensionManager()->enable($name) === false) {
                                return;
                            }
                        }
                        break;
                    case 'disable':
                        /**
                         * Notifies just before an Extension is to be disabled.
                         *
                         * @delegate ExtensionPreDisable
                         * @since Symphony 2.2
                         * @param string $context
                         * '/system/extensions/'
                         * @param array $extensions
                         *  An array of all the extension name's to be disabled, passed by reference
                         */
                        Symphony::ExtensionManager()->notifyMembers('ExtensionPreDisable', '/system/extensions/', array('extensions' => &$checked));

                        foreach ($checked as $name) {
                            Symphony::ExtensionManager()->disable($name);
                        }
                        break;
                    case 'uninstall':
                        /**
                         * Notifies just before an Extension is to be uninstalled
                         *
                         * @delegate ExtensionPreUninstall
                         * @since Symphony 2.2
                         * @param string $context
                         * '/system/extensions/'
                         * @param array $extensions
                         *  An array of all the extension name's to be uninstalled, passed by reference
                         */
                        Symphony::ExtensionManager()->notifyMembers('ExtensionPreUninstall', '/system/extensions/', array('extensions' => &$checked));

                        foreach ($checked as $name) {
                            Symphony::ExtensionManager()->uninstall($name);
                        }
                        break;
                }

                redirect(Administration::instance()->getCurrentPageURL());
            } catch (Exception $e) {
                $this->pageAlert($e->getMessage(), Alert::ERROR);
            }
        }
    }
}
