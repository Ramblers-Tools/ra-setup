<?php

defined('_JEXEC') or die;

namespace Ramblers\Component\Ra_setup\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

class SetupHelper {

    public static function getGreeting() {
        return 'RA Setup initialised';
    }

    /*
      return an array of the nearest organisations and their distances
      $rows = $setupHelper->getNearestOrganisations($id);

      foreach ($rows as $row) {
      echo $row->group_code;
      echo ' - ' . htmlspecialchars($row->name);
      echo ' - ' . number_format($row->distance, 2) .
      "Miles<br>";
      }
     */

    public function getNearestOrganisations(int $organisationId, $type = 'group', int $limit = 5): array {
// First get the latitude and longitude of the selected organisation
        $sql = 'SELECT latitude, longitude FROM #__ra_' . $type . 's WHERE id = ' . (int) $organisationId;

        $org = $this->toolsHelper->getItem($sql);

        if (!$org) {
            $this->app->enqueueMessage('Group not found for ' . $organisationid, 'warning');
            return false;
        }

        $lat = (float) $org->latitude;
        $lon = (float) $org->longitude;

// Earth's radius in miles
        $earthRadius = 6371;

// Find the five nearest organisations
        $sql = 'SELECT id, group_code, name, latitude, longitude,
    (
        {$earthRadius} * ACOS(
            COS(RADIANS($lat))
            * COS(RADIANS(latitude))
            * COS(RADIANS(longitude) - RADIANS($lon))
            + SIN(RADIANS($lat))
            * SIN(RADIANS(latitude))
        )
    ) AS distance ';
        $sql .= 'FROM #__ra_' . $type . 's ';
        $sql .= 'WHERE id <> ' . (int) $organisationId . ' ';
        $sql .= 'ORDER BY distance ASC LIMIT 5';

        return $this->toolsHelper->getRows($sql);
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

            return true;
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Convenience wrapper for updating a single parameter.
     */
    function updateComponentParam(string $component, string $param, $value): bool {
        return updateComponentParams($component, [$param => $value]);
    }

}
