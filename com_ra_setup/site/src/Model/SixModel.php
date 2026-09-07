<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Table\Category;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Contact\Administrator\Table\ContactTable;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class SixModel extends FormModel {

    protected $personHelper;
    protected $toolsHelper;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null) {
        parent::__construct($config, $factory);
        $this->personHelper = new PersonHelper;
        $this->toolsHelper = new ToolsHelper;
    }

    public function getForm($data = [], $loadData = true) {
        return $this->loadForm(
                        'com_ra_setup.six',
                        'six',
                        ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    public function getPeople(): array {
        $source = Factory::getApplication()->getUserState('com_ra_setup.five.data', []);

        if (empty($source['chair']) || empty($source['webmaster'])) {
            return [];
        }

        $people = [];
        $singleRoles = [
            'chair' => ['Chair'],
            'webmaster' => ['Webmaster'],
            'membership_secretary' => ['Membership Secretary'],
            'secretary' => ['Secretary'],
            'treasurer' => ['Treasurer'],
            'walks_coordinator' => ['Walks coordinator'],
        ];

        foreach ($singleRoles as $field => $roles) {
            $this->addPerson($people, (string) ($source[$field] ?? ''), $roles);
        }

        foreach ($this->splitNames((string) ($source['committee_members'] ?? '')) as $name) {
            $this->addPerson($people, $name, ['Committee']);
        }

        uasort($people, static fn(array $a, array $b) => strnatcasecmp($a['short_name'], $b['short_name']));
        $saved = Factory::getApplication()->getUserState('com_ra_setup.six.form', []);

        foreach ($people as $key => &$person) {
            if (isset($saved[$key]) && is_array($saved[$key])) {
                $person['full_name'] = trim((string) ($saved[$key]['name'] ?? $person['full_name']));
                $person['email'] = trim((string) ($saved[$key]['email'] ?? ''));
                $person['permissions'] = $this->normalisePermissions($saved[$key]);
            } else {
                $stored = $this->getStoredPerson($person['short_name']);
                $person['full_name'] = $stored['name'] ?? $person['short_name'];
                $person['email'] = $stored['email'] ?? '';
                $person['permissions'] = $stored['permissions'] ?? $person['permissions'];
            }
        }
        unset($person);

        return $people;
    }

    public function validatePeople(array $submitted): array {
        $expected = $this->getPeople();

        if (empty($expected)) {
            throw new \InvalidArgumentException('Complete Step 5 before saving committee details.');
        }

        $clean = [];
        $emails = [];

        foreach ($expected as $key => $person) {
            $row = $submitted[$key] ?? [];
            $name = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($row['name'] ?? ''))));
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($name === '') {
                throw new \InvalidArgumentException('A full name is required for ' . $person['short_name'] . '.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Enter a valid email address for ' . $name . '.');
            }

            if (isset($emails[$email]) && $emails[$email] !== $key) {
                throw new \InvalidArgumentException('Each person must have a different email address.');
            }

            $emails[$email] = $key;
            $clean[$key] = $person;
            $clean[$key]['full_name'] = $name;
            $clean[$key]['email'] = $email;
            $clean[$key]['permissions'] = $this->normalisePermissions($row);

        }

        return $clean;
    }

    public function persistPeople(array $people): array {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $homeGroup = trim((string) ComponentHelper::getParams('com_ra_tools')->get('default_group', ''));

        if (!preg_match('/^[A-Za-z0-9]{4}$/', $homeGroup)) {
            throw new \RuntimeException('RA Tools default_group must contain a valid four-character group code.');
        }

        $categoryId = $this->getCommitteeCategoryId();
        $users = [];
        $messages = [];
        $warnings = [];

        foreach ($people as $key => $person) {
            $existingUserId = (int) $this->toolsHelper->getValue(
                    'SELECT id FROM #__users WHERE LOWER(email) = '
                    . $db->quote(strtolower($person['email'])) . ' LIMIT 1'
            );

            $userId = $this->personHelper->saveUser($person['full_name'], $person['email']);

            if (in_array('Webmaster', $person['roles'], true) && !$this->isEnabledSuperUser($userId)) {
                $warnings[] = 'The Webmaster account for ' . $person['full_name']
                        . ' is not an enabled Joomla Super User. Please update it manually.';
            }

            if ($existingUserId < 1) {
                $messages[] = 'User created: ' . $person['full_name'] . ' (' . $person['email'] . ').';
            }

            $groupChange = $this->syncUserGroup($userId, 'Registered', true);

            if ($groupChange === null) {
                throw new \RuntimeException('The Joomla Registered user group was not found.');
            }

            $this->addGroupChangeMessage($messages, $groupChange, $person['full_name'], 'Registered');

            $users[$key] = $userId;
            $profileExists = (int) $this->toolsHelper->getValue(
                    'SELECT COUNT(*) FROM #__ra_profiles WHERE id = ' . $userId
            ) > 0;
            $this->personHelper->saveProfile($userId, $person['full_name'], strtoupper($homeGroup));

            if (!$profileExists) {
                $messages[] = 'Profile created for ' . $person['full_name'] . '.';
            }

            $contactExists = (int) $this->toolsHelper->getValue(
                    'SELECT COUNT(*) FROM #__contact_details WHERE user_id = ' . $userId
                    . ' AND catid = ' . $categoryId
            ) > 0;
            $contactPerson = $person;
            $contactPerson['roles'] = $this->normaliseContactRoles($person['roles']);
            $this->personHelper->saveContact($userId, $contactPerson, $categoryId);

            if (!$contactExists) {
                $messages[] = 'Contact created for ' . $person['full_name'] . '.';
            }
        }

        foreach ($people as $key => $person) {
            $groups = [];

            if (array_intersect(['Chair', 'Membership Secretary'], $person['roles'])) {
                $groups[] = 'com_ra_tools';
            }

            foreach (array_unique($groups) as $groupTitle) {
                $groupChange = $this->syncUserGroup($users[$key], $groupTitle, true);

                if ($groupChange === null) {
                    $warnings[] = 'User group ' . $groupTitle . ' was not found; ' . $person['full_name']
                            . ' could not be added to it.';
                } else {
                    $this->addGroupChangeMessage($messages, $groupChange, $person['full_name'], $groupTitle);
                }
            }

            $permissionGroups = [
                'mailman' => 'com_ra_mailman',
                'events' => 'com_ra_events',
                'members' => 'com_ra_members',
            ];

            foreach ($permissionGroups as $permission => $groupTitle) {
                if (!$this->isPermissionAvailable($permission)) {
                    continue;
                }

                $allowed = !empty($person['permissions'][$permission]);

                $groupChange = $this->syncUserGroup($users[$key], $groupTitle, $allowed);

                if ($groupChange === null) {
                    $warnings[] = 'User group ' . $groupTitle . ' was not found; access for '
                            . $person['full_name'] . ' could not be updated.';
                } else {
                    $this->addGroupChangeMessage($messages, $groupChange, $person['full_name'], $groupTitle);
                }

                if ($allowed) {
                    $toolsGroupChange = $this->syncUserGroup($users[$key], 'com_ra_tools', true);

                    if ($toolsGroupChange === null) {
                        $warnings[] = 'User group com_ra_tools was not found; ' . $person['full_name']
                                . ' could not be given supporting access.';
                    } else {
                        $this->addGroupChangeMessage(
                                $messages,
                                $toolsGroupChange,
                                $person['full_name'],
                                'com_ra_tools'
                        );
                    }
                }
            }
        }

        $messages = array_merge($messages, $this->unpublishMissingContacts($categoryId, $users));

        if (ComponentHelper::isEnabled('com_ra_mailman')) {
            $this->saveMailmanAccess($people, $users);
        }

        return [
            'messages' => array_values(array_unique($messages)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function isEnabledSuperUser(int $userId): bool {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__users', 'u'))
                ->innerJoin(
                        $db->quoteName('#__user_usergroup_map', 'm')
                        . ' ON ' . $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id')
                )
                ->innerJoin(
                        $db->quoteName('#__usergroups', 'g')
                        . ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('m.group_id')
                )
                ->where($db->quoteName('u.id') . ' = ' . $userId)
                ->where($db->quoteName('u.block') . ' = 0')
                ->where($db->quoteName('g.title') . ' = ' . $db->quote('Super Users'));

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    private function normaliseContactRoles(array $roles): array {
        return array_values(array_filter(
                        $roles,
                        static fn($role) => !in_array(strtolower(trim((string) $role)), ['events', 'mailman'], true)
        ));
    }

    private function addPerson(array &$people, string $name, array $roles): void {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        if ($name === '') {
            return;
        }

        $identity = strtolower($name);
        $key = substr(sha1($identity), 0, 12);

        if (!isset($people[$key])) {
            $people[$key] = [
                'short_name' => $name,
                'full_name' => $name,
                'email' => '',
                'roles' => [],
                'permissions' => [
                    'mailman' => false,
                    'events' => false,
                    'members' => false,
                ],
            ];
        }

        $people[$key]['roles'] = array_values(array_unique(array_merge($people[$key]['roles'], $roles)));
    }

    private function splitNames(string $value): array {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public function getMailmanEnabled(): bool {
        return ComponentHelper::isEnabled('com_ra_mailman');
    }

    public function getEventsEnabled(): bool {
        return ComponentHelper::isEnabled('com_ra_events');
    }

    public function getMembersEnabled(): bool {
        return ComponentHelper::isEnabled('com_ra_members');
    }

    private function isPermissionAvailable(string $permission): bool {
        return match ($permission) {
            'mailman' => $this->getMailmanEnabled(),
            'events' => $this->getEventsEnabled(),
            'members' => $this->getMembersEnabled(),
            default => false,
        };
    }

    private function normalisePermissions(array $row): array {
        return [
            'mailman' => $this->getMailmanEnabled() && (string) ($row['mailman'] ?? '0') === '1',
            'events' => $this->getEventsEnabled() && (string) ($row['events'] ?? '0') === '1',
            'members' => $this->getMembersEnabled() && (string) ($row['members'] ?? '0') === '1',
        ];
    }

    private function syncUserGroup(int $userId, string $title, bool $enabled): ?string {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $groupId = (int) $this->toolsHelper->getValue(
                'SELECT id FROM #__usergroups WHERE title = ' . $db->quote($title) . ' LIMIT 1'
        );

        if ($groupId < 1) {
            return null;
        }

        $exists = (int) $this->toolsHelper->getValue(
                'SELECT COUNT(*) FROM #__user_usergroup_map WHERE user_id = ' . $userId
                . ' AND group_id = ' . $groupId
        ) > 0;

        if ($enabled) {
            if ($exists) {
                return 'unchanged';
            }

            if (!$this->personHelper->addUserToGroup($userId, $title)) {
                return null;
            }

            return 'added';
        }

        if (!$exists) {
            return 'unchanged';
        }

        $query = $db->getQuery(true)
                ->delete($db->quoteName('#__user_usergroup_map'))
                ->where($db->quoteName('user_id') . ' = ' . $userId)
                ->where($db->quoteName('group_id') . ' = ' . $groupId);
        $db->setQuery($query)->execute();

        return 'removed';
    }

    private function addGroupChangeMessage(
            array &$messages,
            string $change,
            string $personName,
            string $groupTitle
    ): void {
        if ($change === 'added') {
            $messages[] = 'User ' . $personName . ' added to security group ' . $groupTitle . '.';
        } elseif ($change === 'removed') {
            $messages[] = 'User ' . $personName . ' removed from security group ' . $groupTitle . '.';
        }
    }

    private function unpublishMissingContacts(int $categoryId, array $users): array {
        if (empty($users)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('name')])
                ->from($db->quoteName('#__contact_details'))
                ->where($db->quoteName('catid') . ' = ' . $categoryId)
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('user_id') . ' NOT IN ('
                        . implode(', ', array_map('intval', array_values($users))) . ')');
        $db->setQuery($query);
        $contacts = $db->loadObjectList();
        $messages = [];

        foreach ($contacts as $contact) {
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__contact_details'))
                    ->set($db->quoteName('published') . ' = 0')
                    ->where($db->quoteName('id') . ' = ' . (int) $contact->id);
            $db->setQuery($query)->execute();
            $messages[] = 'Contact unpublished: ' . (string) $contact->name . '.';
        }

        return $messages;
    }

    private function getStoredPerson(string $shortName): array {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $sql = 'SELECT u.id, u.name, u.email FROM #__users AS u '
                . 'INNER JOIN #__contact_details AS c ON c.user_id = u.id '
                . 'INNER JOIN #__categories AS cat ON cat.id = c.catid '
                . 'WHERE cat.extension = "com_contact" AND LOWER(cat.title) = "committee" '
                . 'AND (LOWER(c.name) = ' . $db->quote(strtolower($shortName))
                . ' OR LOWER(SUBSTRING_INDEX(c.name, " ", 1)) = ' . $db->quote(strtolower($shortName)) . ') '
                . 'ORDER BY c.id DESC LIMIT 1';
        $row = $this->toolsHelper->getItem($sql);

        if (!$row) {
            return [];
        }

        return [
            'name' => (string) $row->name,
            'email' => (string) $row->email,
            'permissions' => $this->getStoredPermissions((int) $row->id),
        ];
    }

    private function getStoredPermissions(int $userId): array {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $permissions = [
            'mailman' => false,
            'events' => false,
            'members' => false,
        ];
        $groupRows = $this->toolsHelper->getRows(
                'SELECT g.title FROM #__usergroups AS g '
                . 'INNER JOIN #__user_usergroup_map AS m ON m.group_id = g.id '
                . 'WHERE m.user_id = ' . $userId
                . ' AND g.title IN ('
                . $db->quote('com_ra_mailman') . ', '
                . $db->quote('com_ra_events') . ', '
                . $db->quote('com_ra_members') . ')'
        );

        if ($groupRows === false) {
            throw new \RuntimeException('Unable to load the existing component permissions.');
        }

        $permissionByGroup = [
            'com_ra_mailman' => 'mailman',
            'com_ra_events' => 'events',
            'com_ra_members' => 'members',
        ];

        foreach ($groupRows as $row) {
            if (isset($permissionByGroup[$row->title])) {
                $permissions[$permissionByGroup[$row->title]] = true;
            }
        }

        if ($this->getMailmanEnabled()) {
            $author = (int) $this->toolsHelper->getValue(
                    'SELECT COUNT(*) FROM #__ra_mail_subscriptions '
                    . 'WHERE user_id = ' . $userId . ' AND record_type = 2 AND state = 1'
            );
            $permissions['mailman'] = $permissions['mailman'] || $author > 0;
        }

        return $permissions;
    }

    private function getCommitteeCategoryId(): int {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $categoryId = (int) $this->toolsHelper->getValue(
                        'SELECT id FROM #__categories WHERE extension = "com_contact" '
                        . 'AND LOWER(title) = "committee" ORDER BY id LIMIT 1'
        );

        if ($categoryId > 0) {
            return $categoryId;
        }

        $category = new Category($db);
        $category->setLocation(1, 'last-child');

        if (!$category->bind([
                    'parent_id' => 1,
                    'extension' => 'com_contact',
                    'title' => 'Committee',
                    'alias' => 'committee',
                    'published' => 1,
                    'access' => 1,
                    'language' => '*',
                    'params' => '{}',
                    'metadata' => '{}',
                ]) || !$category->check() || !$category->store()) {
            throw new \RuntimeException('Unable to create the Committee contact category: ' . $category->getError());
        }

        return (int) $category->id;
    }

    private function saveMailmanAccess(array $people, array $users): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $listRows = $this->toolsHelper->getRows('SELECT id, name FROM #__ra_mail_lists ORDER BY id');

        if ($listRows === false) {
            throw new \RuntimeException('Unable to load RA Mailman mailing lists.');
        }

        $chairIds = [];
        $authorIds = [];
        $webmasterIds = [];

        foreach ($people as $key => $person) {
            if (in_array('Chair', $person['roles'], true)) {
                $chairIds[] = $users[$key];
            }
            if (in_array('Webmaster', $person['roles'], true)) {
                $webmasterIds[] = $users[$key];
            }
            if (!empty($person['permissions']['mailman'])) {
                $authorIds[] = $users[$key];
            }
        }

        $chairId = (int) reset($chairIds);

        foreach ($listRows as $list) {
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ra_mail_lists'))
                    ->set($db->quoteName('owner_id') . ' = ' . $chairId)
                    ->where($db->quoteName('id') . ' = ' . (int) $list->id);
            $db->setQuery($query)->execute();
            $this->saveSubscription((int) $list->id, $chairId, 3);

            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ra_mail_subscriptions'))
                    ->set($db->quoteName('state') . ' = 0')
                    ->where($db->quoteName('list_id') . ' = ' . (int) $list->id)
                    ->where($db->quoteName('record_type') . ' = 2')
                    ->where($db->quoteName('user_id') . ' IN (' . implode(', ', array_map('intval', $users)) . ')');
            $db->setQuery($query)->execute();

            foreach (array_unique($authorIds) as $userId) {
                $this->saveSubscription((int) $list->id, (int) $userId, 2);
            }

            if (strcasecmp((string) $list->name, 'RT Users') === 0) {
                foreach (array_unique(array_merge([$chairId], $webmasterIds)) as $userId) {
                    $this->saveSubscription((int) $list->id, (int) $userId, 1);
                }
            }
        }
    }

    private function saveSubscription(int $listId, int $userId, int $recordType): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $id = (int) $this->toolsHelper->getValue(
                        'SELECT id FROM #__ra_mail_subscriptions WHERE list_id = ' . $listId
                        . ' AND user_id = ' . $userId . ' AND record_type = ' . $recordType . ' LIMIT 1'
        );
        $now = Factory::getDate()->toSql();
        $actorId = (int) Factory::getApplication()->getIdentity()->id;

        if ($id > 0) {
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ra_mail_subscriptions'))
                    ->set($db->quoteName('state') . ' = 1')
                    ->set($db->quoteName('method_id') . ' = 2')
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('modified_by') . ' = ' . $actorId)
                    ->where($db->quoteName('id') . ' = ' . $id);
            $db->setQuery($query)->execute();

            return;
        }

        $subscription = (object) [
                    'list_id' => $listId,
                    'user_id' => $userId,
                    'record_type' => $recordType,
                    'method_id' => 2,
                    'state' => 1,
                    'ip_address' => '',
                    'created' => $now,
                    'created_by' => $actorId,
        ];
        $db->insertObject('#__ra_mail_subscriptions', $subscription);
    }

}
