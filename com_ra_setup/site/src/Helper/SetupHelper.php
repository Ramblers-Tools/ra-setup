<?php

namespace Ramblers\Component\Ra_setup\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

defined('_JEXEC') or die;

class SetupHelper {

    protected $app;
    protected $db;
    protected $toolsHelper;
    protected $user;

    public function __construct() {
        $this->app = Factory::getApplication();
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->user = $this->app->getSession()->get('user');
    }

    public function assertCanRunWizard(): void {
        $dateCompleted = $this->wizardCompleted();

        if ($dateCompleted === false) {
            throw new \RuntimeException('Unable to determine whether setup has been completed.', 500);
        }

        if ($dateCompleted !== null && !$this->app->getIdentity()->authorise('core.admin')) {
            throw new \RuntimeException(
                            'Configuration was completed ' . $dateCompleted . ' and can only be updated by a Super User.',
                            403
            );
        }
    }

    public function assertWizardNotCompleted(): void {
        if ($this->isWizardCompleted()) {
            throw new \RuntimeException('The setup wizard has already been completed.', 404);
        }
    }

    public static function getGreeting() {
        return 'RA Setup initialised';
    }

    public function logWizardStepStart(int $step): void {
        $this->app->enqueueMessage('Step ' . $step . ' of the Wizard started', 'success');
        echo '<h3>Step ' . $step . ' of the Wizard started</h3>';
        $this->toolsHelper->createLog(
                'RA Setup',
                $step,
                $this->app->isClient('administrator') ? 'Admin' : 'Site',
                'Step ' . $step . ' of the Wizard started'
        );
    }

    public function getNearestOrganisations($code, int $limit = 5, $display = 'N') {
        // First get the latitude and longitude of the selected organisation
        if (strlen($code) == 4) {
            $type = 'group';
        } else {
            $type = 'area';
        }
        $sql = 'SELECT latitude, longitude FROM #__ra_' . $type . 's WHERE code = "' . $code . '"';
        $org = $this->toolsHelper->getItem($sql);

        if (!$org) {
            $this->app->enqueueMessage('Group not found for ' . $code, 'warning');
            return false;
        }

        $lat = (float) $org->latitude;
        $lon = (float) $org->longitude;
//        $lat = $org->latitude;
//        $lon = $org->longitude;
        if ($lat == 0) {
            $this->app->enqueueMessage('Latitude is zero for ' . $code, 'warning');
            return false;
        }
        if ($lon == 0) {
            $this->app->enqueueMessage('Longitude is zero for ' . $code, 'warning');
            return false;
        }
        // Earth's radius in miles
        $earthRadius = 6371;

        $limit = max(0, $limit);

        // Find the requested number of nearest organisations.
        $sql = 'SELECT id, code, name, latitude, longitude,
      (
      ' . $earthRadius . ' * ACOS(
      COS(RADIANS(' . $lat . '))
     * COS(RADIANS(latitude))
     * COS(RADIANS(longitude) - RADIANS(' . $lon . '))
      + SIN(RADIANS(' . $lat . '))
     * SIN(RADIANS(latitude))
      )
      ) AS distance ';
        $sql .= 'FROM #__ra_' . $type . 's ';
        $sql .= 'WHERE code <> "' . $code . '" ';
        $sql .= 'ORDER BY distance ASC LIMIT ' . $limit;
        $rows = $this->toolsHelper->getRows($sql);
       if ($display == 'Y') {
            $objTable = new ToolsTable;
            $objTable->add_header("Num,Code, Name,Distance");
            $i=0;
            foreach ($rows as $row) {
                $i++;
                 $objTable->add_item($i);
                $objTable->add_item($row->code);
                $objTable->add_item($row->name);
                $objTable->add_item($row->distance);

                $objTable->generate_line();
            }
        }
        return $this->toolsHelper->getRows($sql);
    }

    public function isWizardCompleted(): bool {
        $dateCompleted = $this->wizardCompleted();

        if ($dateCompleted === false) {
            throw new \RuntimeException('Unable to determine whether setup has been completed.', 500);
        }

        return $dateCompleted !== null;
    }

    /**
     * Convenience wrapper for updating a single parameter.
     */
    function updateComponentParam(string $component, string $param, $value): bool {
        return $this->updateComponentParams($component, [$param => $value]);
    }

    /**
     * Update one or more component parameters.
     *
     * @param string $component Component name (e.g. 'com_ra_tools')
     * @param array  $params    Associative array of parameter => value
     *
     * @return bool True on success, false on failure.
     */
    function updateComponentParams(string $component, array $params): bool {
        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            // Load existing params
            $query = $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($component));

            $db->setQuery($query);
            $paramsJson = $db->loadResult();

            if ($paramsJson === null) {
                return false;    // Component not found
            }

            // Update parameters
            $registry = new Registry($paramsJson);

            foreach ($params as $name => $value) {
                $registry->set($name, $value);
            }

            // Save updated params
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote($registry->toString()))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($component));

            $db->setQuery($query);
            $db->execute();

            // Read directly from the database rather than ComponentHelper's
            // request cache, and do not report success unless every value was
            // actually persisted.
            $query = $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($component));
            $storedJson = $db->setQuery($query)->loadResult();
            $stored = new Registry(is_string($storedJson) ? $storedJson : '');

            foreach ($params as $name => $value) {
                if ((string) $stored->get($name, '') !== (string) $value) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    function updateMaillists($group_code){
        $group_name = $this->toolsHelper->lookupGroup($group_code);
        $sql = 'SELECT * FROM #__ra_mail_lists WHERE state=1';
        $rows = $this->toolsHelper->getRows($sql);
        if (count($rows) == 0) {
            $this->app->enqueueMessage('No mail lists found', 'warning');
            return false;
        }
        $this->app->enqueueMessage('Updating ' . count($rows) . ' mail lists to group ' . $group_code, 'warning');  
        foreach ($rows as $row) {
            $this->app->enqueueMessage('Updating ' . $row->name . ' to group ' . $group_code, 'warning');  
//            $this->app->enqueueMessage('primary ' . $row->group_primary .', ' . $row->group_code . ' ,name: ' . $row->name . ' sql: ' . $sql, 'warning');
            $sql = 'UPDATE #__ra_mail_lists SET group_code = "' . $group_code . '"';
            if (strtoupper($row->name) == "MEMBERS NEWSLETTER") {
                $sql .= ', group_primary = "' . $this->db->quote($group_code) . '"';
                $sql .= ', footer=' . $this->db->quote('You have received this message as a member of ' . $group_name . ' Ramblers');
            } elseif (strtoupper($row->name) == "COMMITTEE MEMBERS") {
                $sql .= ', footer=' . $this->db->quote('You have received this message as a Committee member of ' . $group_name . ' Ramblers');
            }
            $sql .= ' WHERE id = ' . $row->id;
            if (JDEBUG) {
                $this->app->enqueueMessage($sql, 'warning');
            }
            $this->toolsHelper->executeCommand($sql);
        }

        return true;
    }

    /**
     * Update parameters on every instance of a site module.
     *
     * @param string $module Module name (e.g. 'mod_raheader')
     * @param array  $params Associative array of parameter => value
     *
     * @return bool True on success, false if the module was not found or saving failed.
     */
    function updateModuleParams(string $module, array $params): bool {
        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('id'),
                        $db->quoteName('params'),
                    ])
                    ->from($db->quoteName('#__modules'))
                    ->where($db->quoteName('module') . ' = ' . $db->quote($module))
                    ->where($db->quoteName('client_id') . ' = 0');

            $db->setQuery($query);
            $modules = $db->loadObjectList();

            if (empty($modules)) {
                return false;
            }

            foreach ($modules as $moduleRecord) {
                $registry = new Registry($moduleRecord->params);

                foreach ($params as $name => $value) {
                    $registry->set($name, $value);
                }

                $query = $db->getQuery(true)
                        ->update($db->quoteName('#__modules'))
                        ->set($db->quoteName('params') . ' = ' . $db->quote($registry->toString()))
                        ->where($db->quoteName('id') . ' = ' . (int) $moduleRecord->id);

                $db->setQuery($query);
                $db->execute();
            }

            return true;
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    public function wizardCompleted() {
        $sql = 'SELECT key_value FROM #__ra_control WHERE record_type=3';
        return $this->toolsHelper->getValue($sql);
    }

}
