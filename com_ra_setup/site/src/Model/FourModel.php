<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class FourModel extends FormModel
{
    private const EXTENSION_GROUPS = [
        'events' => [
            ['name' => 'com_ra_events', 'type' => 'component', 'element' => 'com_ra_events'],
            ['name' => 'mod_ra_events', 'type' => 'module', 'element' => 'mod_ra_events'],
            ['name' => 'plg_ra_events', 'type' => 'plugin', 'element' => 'ra_events', 'folder' => 'webservices'],
            [
                'name' => 'plg_ra_eventscli',
                'type' => 'plugin',
                'element' => ['ra_eventscli', 'ra_events'],
                'folder' => 'console',
            ],
        ],
        'mailman' => [
            ['name' => 'com_ra_mailman', 'type' => 'component', 'element' => 'com_ra_mailman'],
            ['name' => 'com_ra_delivery', 'type' => 'component', 'element' => 'com_ra_delivery'],
            ['name' => 'com_ra_members', 'type' => 'component', 'element' => 'com_ra_members'],
            ['name' => 'plg_ra_mailman', 'type' => 'plugin', 'element' => 'ra_mailman', 'folder' => 'console'],
            ['name' => 'plg_ra_delivery', 'type' => 'plugin', 'element' => 'ra_delivery', 'folder' => 'console'],
        ],
        'sso' => [
            ['name' => 'com_ra_sso', 'type' => 'component', 'element' => 'com_ra_sso'],
        ],
    ];

    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.four',
            'four',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    public function updateExtensionGroups(array $data): array
    {
        $updates = [];
        $missing = [];
        $missingExtensions = [];

        foreach (self::EXTENSION_GROUPS as $groupName => $extensions) {
            $enabled = ($data[$groupName] ?? '0') === '1';

            foreach ($extensions as $extension) {
                $ids = $this->getExtensionIds($extension);

                if ($ids === false) {
                    throw new \RuntimeException('Unable to read the Joomla extension registry.');
                }

                if ($enabled && empty($ids)) {
                    if ($extension['type'] === 'component') {
                        $missing[] = $extension['name'];
                    } else {
                        $missingExtensions[] = $extension['name'];
                    }

                    continue;
                }

                foreach ($ids as $id) {
                    $updates[(int) $id] = $enabled ? 1 : 0;
                }
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'The following required extensions are not installed: ' . implode(', ', array_unique($missing))
            );
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($updates as $extensionId => $enabled) {
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = ' . $enabled)
                    ->where($db->quoteName('extension_id') . ' = ' . $extensionId);

            $db->setQuery($query);
            $db->execute();
        }

        return array_values(array_unique($missingExtensions));
    }

    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ra_setup.four.data', []);

        if (!empty($data)) {
            return $data;
        }

        $data = [];

        foreach (self::EXTENSION_GROUPS as $groupName => $extensions) {
            $ids = $this->getExtensionIds($extensions[0], true);

            if ($ids === false) {
                throw new \RuntimeException('Unable to read the Joomla extension registry.');
            }

            $data[$groupName] = empty($ids) ? '0' : '1';
        }

        return $data;
    }

    private function getExtensionIds(array $extension, bool $enabledOnly = false)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $elements = (array) $extension['element'];
        $sql = 'SELECT extension_id FROM #__extensions ';
        $sql .= 'WHERE type = ' . $db->quote($extension['type']);
        $sql .= ' AND element IN (' . implode(', ', array_map([$db, 'quote'], $elements)) . ')';

        if (isset($extension['folder'])) {
            $sql .= ' AND folder = ' . $db->quote($extension['folder']);
        }

        if ($enabledOnly) {
            $sql .= ' AND enabled = 1';
        }

        $rows = (new ToolsHelper)->getRows($sql);

        if ($rows === false) {
            return false;
        }

        return array_map(static fn($row) => (int) $row->extension_id, $rows);
    }
}
