<?php

/**
 * Installation script for com_ra_setup.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

class Com_Ra_setupInstallerScript
{
    private $minimumJoomlaVersion = '5.0';
    private $minimumPHPVersion = '7.4.0';
    private $minimumToolsVersion = '4.0.13';
    private $minimumDeliveryVersion = '1.0.12-dev';

    function buildButton($url, $text, $newWindow = 0, $colour = '') {
        if ($colour == '') {
            $colour = 'sunrise';
        }
        $class = 'link-button ' . $colour;
        //       echo "colour=$colour, code=$code, class=$class<br>";
        $q = chr(34);
        $out = "<a class=" . $q . $class . $q;
        $out .= " href=" . $q . $url . $q;
        $out .= " target =" . $q . "_self" . $q;
        $out .= ">";
        $out .= $text;
        $out .= "</a>";
        return $out;
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field, using data supplied in $details
//  $mode = U: update the field (keeping name the same), using $details
//  $mode = D: delete the field

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
//        echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            return $this->fail('Installer could not update missing field ' . $table_name . '.' . $column . '.');
        }

        $sql = 'ALTER TABLE ' . $table_name . ' ';
        if ($mode == 'A') {
            $sql .= 'ADD ' . $column . ' ';
            $sql .= $details;
        } elseif ($mode == 'D') {
            $sql .= 'DROP ' . $column;
        } elseif ($mode == 'U') {
            $sql .= 'CHANGE ' . $column . ' ' . $column . ' ';
            $sql .= $details;
        }
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Success';
        } else {
            echo 'Failure';
        }
        echo ' for ' . $table_name . '<br>';
        return $count;
    }

    private function checkColumnExists($table, $column) {
        $config = JFactory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->getValue($sql);
    }
    private function executeCommand($sql) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->execute();
    }

    private function fail(string $message): bool
    {
        Factory::getApplication()->enqueueMessage($message, 'error');
        Log::add($message, Log::ERROR, 'jerror');

        return false;
    }

    private function getValue($sql) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->loadResult();
    }

    /**
     * Returns the installed component and database schema versions.
     *
     * @return object|false Object containing component and db_version, or false if not found.
     */
    public function getVersions($component = 'com_ra_setup', $extensionType = 'component')
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select([
                    $db->quoteName('e.manifest_cache'),
                    $db->quoteName('s.version_id', 'db_version'),
                ])
                ->from($db->quoteName('#__extensions', 'e'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__schemas', 's')
                    . ' ON ' . $db->quoteName('s.extension_id') . ' = ' . $db->quoteName('e.extension_id')
                )
                ->where($db->quoteName('e.element') . ' = ' . $db->quote($component))
                ->where($db->quoteName('e.type') . ' = ' . $db->quote($extensionType));

        $db->setQuery($query);
        $item = $db->loadObject();

        if (!$item) {
            return false;
        }

        $manifest = json_decode($item->manifest_cache);

        if (!$manifest || !isset($manifest->version)) {
            return false;
        }

        $versions = new \stdClass;
        $versions->component = $manifest->version;
        $versions->db_version = $item->db_version;

        return $versions;
    }

    public function install($parent): bool
    {
        Factory::getApplication()->enqueueMessage('Installing RA Setup (com_ra_setup)', 'message');

        return true;
    }

    public function update($parent): bool
    {
        Factory::getApplication()->enqueueMessage('Updating RA Setup (com_ra_setup)', 'message');

        return true;
    }

    public function uninstall($parent): bool
    {
        Factory::getApplication()->enqueueMessage('Uninstalling RA Setup (com_ra_setup)', 'message');
        $versions = $this->getVersions();

        if ($versions !== false) {
            $this->reportVersions('RA Setup', $versions);
        }

        return true;
    }

    public function preflight($type, $parent): bool
    {
        Factory::getApplication()->enqueueMessage( 'RA Delivery: preflight checks', 'info');
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion));
        }

        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion));
        }

        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            return $this->fail('RA Setup requires RA Tools (com_ra_tools) to be installed and enabled.');
        }

        $toolsVersions = $this->getVersions('com_ra_tools');

        if ($toolsVersions === false) {
            return $this->fail('Unable to determine the installed version of RA Tools.');
        }

        $this->reportVersions('RA Tools', $toolsVersions);

        if (version_compare($toolsVersions->component, $this->minimumToolsVersion, '<')) {
            return $this->fail('RA Setup requires RA Tools version ' . $this->minimumToolsVersion
                    . ' or later; found ' . $toolsVersions->component . '.');
        }

        if (!ComponentHelper::isEnabled('com_ra_delivery', true)) {
            return $this->fail('RA Setup requires RA Delivery (com_ra_delivery) to be installed and enabled.');
        }

        $deliveryVersions = $this->getVersions('pkg_ra_delivery', 'package');

        if ($deliveryVersions === false) {
            return $this->fail(
                'Unable to determine the installed RA Delivery package version. '
                    . 'Install pkg_ra_delivery version ' . $this->minimumDeliveryVersion . ' or later.'
            );
        }

        $this->reportVersions('RA Delivery package', $deliveryVersions);

        if (version_compare($deliveryVersions->component, $this->minimumDeliveryVersion, '<')) {
            return $this->fail(
                'RA Setup requires RA Delivery package version ' . $this->minimumDeliveryVersion
                    . ' or later; found ' . $deliveryVersions->component . '.'
            );
        }

        if ($type === 'update') {
            $versions = $this->getVersions();

            if ($versions !== false) {
                $this->reportVersions('Current RA Setup', $versions);
            }
        }

        return true;
    }

    public function postflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }
        Factory::getApplication()->enqueueMessage( 'RA Delivery: postflight checks', 'info');
        $versions = $this->getVersions();

        if ($versions === false) {
            Factory::getApplication()->enqueueMessage(
                'RA Setup (com_ra_setup) was installed, but its installed version could not be determined.',
                'warning'
            );
        } else {
            $this->reportVersions('RA Setup', $versions);
        }

        echo '<p><strong>RA Setup ' . ($type === 'update' ? 'update' : 'installation')
            . ' completed.</strong></p>';
        echo '<p><strong>Useful links</strong></p>';
        echo '<p><a href="index.php?option=com_ra_setup&amp;view=wizard">Open RA Setup</a></p>';
        echo $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false,'granite') . '<br>';
        echo $this->buildButton('index.php?option=com_config&view=component&component=com_ra_setup','Configure RA Setup');
        return true;
    }

    private function reportVersions(string $label, object $versions): void
    {
        $databaseVersion = $versions->db_version ?: 'not recorded';

        echo '<p>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        echo ' version ' . htmlspecialchars($versions->component, ENT_QUOTES, 'UTF-8');
        echo ', database version ' . htmlspecialchars($databaseVersion, ENT_QUOTES, 'UTF-8');
        echo '</p>';
    }
}
