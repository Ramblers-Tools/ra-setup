<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class FiveModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.five',
            'five',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    public function sanitise(array $data): array
    {
        $singleFields = [
            'chair' => true,
            'webmaster' => true,
            'membership_secretary' => false,
            'secretary' => false,
            'treasurer' => false,
            'walks_coordinator' => false,
        ];
        $clean = [];

        foreach ($singleFields as $field => $required) {
            $value = $this->normaliseName((string) ($data[$field] ?? ''));

            if ($required && $value === '') {
                throw new \InvalidArgumentException(ucwords(str_replace('_', ' ', $field)) . ' is required.');
            }

            if (str_contains($value, ',')) {
                throw new \InvalidArgumentException(
                    ucwords(str_replace('_', ' ', $field)) . ' must contain one person only.'
                );
            }

            $clean[$field] = $value;
        }

        foreach (['committee_members'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $names = $value === '' ? [] : array_map([$this, 'normaliseName'], explode(',', $value));

            if (in_array('', $names, true)) {
                throw new \InvalidArgumentException(
                    ucwords(str_replace('_', ' ', $field)) . ' contains an empty name.'
                );
            }

            $clean[$field] = implode(', ', array_values(array_unique($names)));
        }

        return $clean;
    }

    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $state = $app->getUserState('com_ra_setup.five.data', []);

        if (!empty($state)) {
            return $state;
        }

        return $this->loadStoredNames();
    }

    private function loadStoredNames(): array
    {
        $data = [
            'chair' => '',
            'webmaster' => '',
            'membership_secretary' => '',
            'secretary' => '',
            'treasurer' => '',
            'walks_coordinator' => '',
            'committee_members' => '',
        ];
        $sql = 'SELECT c.name, c.con_position FROM #__contact_details AS c '
            . 'INNER JOIN #__categories AS cat ON cat.id = c.catid '
            . 'WHERE c.published = 1 AND cat.extension = "com_contact" '
            . 'AND LOWER(cat.title) = "committee" ORDER BY c.name';
        $rows = (new ToolsHelper)->getRows($sql);

        if ($rows === false) {
            return $data;
        }

        $roleFields = [
            'chair' => 'chair',
            'webmaster' => 'webmaster',
            'membership secretary' => 'membership_secretary',
            'secretary' => 'secretary',
            'treasurer' => 'treasurer',
            'walks coordinator' => 'walks_coordinator',
        ];
        $committeeMembers = [];

        foreach ($rows as $row) {
            $name = $this->normaliseName((string) $row->name);
            $roles = preg_split('/\s*\+\s*/', strtolower((string) $row->con_position), -1, PREG_SPLIT_NO_EMPTY);
            $hasNamedCommitteeRole = false;

            foreach ($roles as $role) {
                if (isset($roleFields[$role])) {
                    $data[$roleFields[$role]] = $name;
                    $hasNamedCommitteeRole = true;
                } elseif ($role === 'committee') {
                    $hasNamedCommitteeRole = true;
                }
            }

            if ($hasNamedCommitteeRole && empty(array_intersect($roles, array_keys($roleFields)))) {
                $committeeMembers[] = $name;
            }
        }

        $data['committee_members'] = implode(', ', array_values(array_unique($committeeMembers)));

        return $data;
    }

    private function normaliseName(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($name)));
    }
}
