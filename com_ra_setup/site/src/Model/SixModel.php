<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Category;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Contact\Administrator\Table\ContactTable;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class SixModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.six',
            'six',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    public function getPeople(): array
    {
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

        foreach ($this->splitNames((string) ($source['mailshot_senders'] ?? '')) as $name) {
            $this->addPerson($people, $name, ['Mailman']);
        }

        foreach ($this->splitNames((string) ($source['event_creators'] ?? '')) as $name) {
            $this->addPerson($people, $name, ['Events']);
        }

        uasort($people, static fn(array $a, array $b) => strnatcasecmp($a['short_name'], $b['short_name']));
        $saved = Factory::getApplication()->getUserState('com_ra_setup.six.form', []);

        foreach ($people as $key => &$person) {
            if (isset($saved[$key]) && is_array($saved[$key])) {
                $person['full_name'] = trim((string) ($saved[$key]['name'] ?? $person['full_name']));
                $person['email'] = trim((string) ($saved[$key]['email'] ?? ''));
            } else {
                $stored = $this->getStoredPerson($person['short_name']);
                $person['full_name'] = $stored['name'] ?? $person['short_name'];
                $person['email'] = $stored['email'] ?? '';
            }
        }
        unset($person);

        return $people;
    }

    public function validatePeople(array $submitted): array
    {
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
        }

        return $clean;
    }

    public function persistPeople(array $people): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $homeGroup = trim((string) ComponentHelper::getParams('com_ra_tools')->get('default_group', ''));

        if (!preg_match('/^[A-Za-z0-9]{4}$/', $homeGroup)) {
            throw new \RuntimeException('RA Tools default_group must contain a valid four-character group code.');
        }

        $categoryId = $this->getCommitteeCategoryId();
        $users = [];

        foreach ($people as $key => $person) {
            $userId = $this->saveUser($person['full_name'], $person['email']);

            if (!$this->addUserToGroup($userId, 'Registered')) {
                throw new \RuntimeException('The Joomla Registered user group was not found.');
            }

            $users[$key] = $userId;
            $this->saveProfile($userId, $person['full_name'], $person['email'], strtoupper($homeGroup));
            $this->saveContact($userId, $person, $categoryId);
        }

        $warnings = [];

        foreach ($people as $key => $person) {
            $groups = [];

            if (in_array('Webmaster', $person['roles'], true)) {
                $groups[] = 'Super Users';
            }

            if (in_array('Chair', $person['roles'], true)) {
                $groups = array_merge($groups, ['com_ra_tools', 'com_ra_events', 'com_ra_mailman', 'com_ra_members']);
            }

            if (in_array('Membership Secretary', $person['roles'], true)) {
                $groups = array_merge($groups, ['com_ra_tools', 'com_ra_members']);
            }

            if (in_array('Events', $person['roles'], true)) {
                $groups = array_merge($groups, ['com_ra_tools', 'com_ra_events']);
            }

            foreach (array_unique($groups) as $groupTitle) {
                if (!$this->addUserToGroup($users[$key], $groupTitle)) {
                    $warnings[] = 'User group ' . $groupTitle . ' was not found; ' . $person['full_name']
                        . ' could not be added to it.';
                }
            }
        }

        if (ComponentHelper::isEnabled('com_ra_mailman')) {
            $this->saveMailmanAccess($people, $users);
        }

        return array_values(array_unique($warnings));
    }

    private function addPerson(array &$people, string $name, array $roles): void
    {
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
            ];
        }

        $people[$key]['roles'] = array_values(array_unique(array_merge($people[$key]['roles'], $roles)));
    }

    private function splitNames(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function getStoredPerson(string $shortName): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $sql = 'SELECT u.name, u.email FROM #__users AS u '
            . 'INNER JOIN #__contact_details AS c ON c.user_id = u.id '
            . 'INNER JOIN #__categories AS cat ON cat.id = c.catid '
            . 'WHERE cat.extension = "com_contact" AND LOWER(cat.title) = "committee" '
            . 'AND (LOWER(c.name) = ' . $db->quote(strtolower($shortName))
            . ' OR LOWER(SUBSTRING_INDEX(c.name, " ", 1)) = ' . $db->quote(strtolower($shortName)) . ') '
            . 'ORDER BY c.id DESC LIMIT 1';
        $row = (new ToolsHelper)->getItem($sql);

        return $row ? ['name' => (string) $row->name, 'email' => (string) $row->email] : [];
    }

    private function saveUser(string $name, string $email): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $helper = new ToolsHelper;
        $sql = 'SELECT id FROM #__users WHERE LOWER(email) = ' . $db->quote(strtolower($email)) . ' LIMIT 1';
        $userId = (int) $helper->getValue($sql);

        if ($userId > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__users'))
                ->set($db->quoteName('name') . ' = ' . $db->quote($name))
                ->where($db->quoteName('id') . ' = ' . $userId);
            $db->setQuery($query)->execute();

            return $userId;
        }

        $usernameOwner = (int) $helper->getValue(
            'SELECT id FROM #__users WHERE LOWER(username) = ' . $db->quote(strtolower($email)) . ' LIMIT 1'
        );

        if ($usernameOwner > 0) {
            throw new \RuntimeException('The username ' . $email . ' is already used by another account.');
        }

        $now = Factory::getDate()->toSql();
        $password = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $record = (object) [
            'name' => $name,
            'username' => $email,
            'email' => $email,
            'password' => $password,
            'block' => 0,
            'sendEmail' => 0,
            'registerDate' => $now,
            'activation' => '',
            'params' => '{}',
            'requireReset' => 1,
        ];
        $db->insertObject('#__users', $record);
        $userId = (int) $db->insertid();

        if ($userId < 1) {
            throw new \RuntimeException('Unable to create the Joomla user for ' . $name . '.');
        }

        return $userId;
    }

    private function saveProfile(int $userId, string $name, string $email, string $homeGroup): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $helper = new ToolsHelper;
        $exists = (int) $helper->getValue('SELECT COUNT(*) FROM #__ra_profiles WHERE id = ' . $userId) > 0;
        $now = Factory::getDate()->toSql();
        $actorId = (int) Factory::getApplication()->getIdentity()->id;

        if ($exists) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ra_profiles'))
                ->set($db->quoteName('home_group') . ' = ' . $db->quote($homeGroup))
                ->set($db->quoteName('preferred_name') . ' = ' . $db->quote($name))
                ->set($db->quoteName('email') . ' = ' . $db->quote($email))
                ->set($db->quoteName('state') . ' = 1')
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . $actorId)
                ->where($db->quoteName('id') . ' = ' . $userId);
            $db->setQuery($query)->execute();

            return;
        }

        $columns = $db->getTableColumns('#__ra_profiles', false);
        $record = (object) [
            'id' => $userId,
            'home_group' => $homeGroup,
            'preferred_name' => $name,
            'email' => $email,
            'state' => 1,
            'created' => $now,
            'created_by' => $actorId,
        ];

        if (isset($columns['member_id']) && stripos((string) ($columns['member_id']->Extra ?? ''), 'auto_increment') === false) {
            $nextId = (int) $helper->getValue('SELECT COALESCE(MAX(member_id), 0) + 1 FROM #__ra_profiles');
            $record->member_id = max(1, $nextId);
        }

        $db->insertObject('#__ra_profiles', $record);
    }

    private function addUserToGroup(int $userId, string $title): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $helper = new ToolsHelper;
        $groupId = (int) $helper->getValue(
            'SELECT id FROM #__usergroups WHERE title = ' . $db->quote($title) . ' LIMIT 1'
        );

        if ($groupId < 1) {
            return false;
        }

        $exists = (int) $helper->getValue(
            'SELECT COUNT(*) FROM #__user_usergroup_map WHERE user_id = ' . $userId . ' AND group_id = ' . $groupId
        ) > 0;

        if (!$exists) {
            $db->insertObject('#__user_usergroup_map', (object) ['user_id' => $userId, 'group_id' => $groupId]);
        }

        return true;
    }

    private function getCommitteeCategoryId(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $categoryId = (int) (new ToolsHelper)->getValue(
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

    private function saveContact(int $userId, array $person, int $categoryId): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        Factory::getApplication()->bootComponent('com_contact');
        $contactId = (int) (new ToolsHelper)->getValue(
            'SELECT c.id FROM #__contact_details AS c '
            . 'INNER JOIN #__categories AS cat ON cat.id = c.catid '
            . 'WHERE c.user_id = ' . $userId . ' AND cat.extension = "com_contact" '
            . 'AND LOWER(cat.title) = "committee" ORDER BY c.id LIMIT 1'
        );
        $contact = new ContactTable($db);

        if ($contactId > 0 && !$contact->load($contactId)) {
            throw new \RuntimeException('Unable to load the existing contact for ' . $person['full_name'] . '.');
        }

        if (!$contact->bind([
            'name' => $person['full_name'],
            'alias' => strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $person['full_name']))) . '-' . $userId,
            'con_position' => implode('+', $person['roles']),
            'email_to' => $person['email'],
            'user_id' => $userId,
            'catid' => $categoryId,
            'published' => 1,
            'access' => 1,
            'language' => '*',
            'params' => '{}',
            'metadata' => '{}',
        ]) || !$contact->check() || !$contact->store()) {
            throw new \RuntimeException('Unable to save the contact for ' . $person['full_name'] . ': ' . $contact->getError());
        }
    }

    private function saveMailmanAccess(array $people, array $users): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $helper = new ToolsHelper;
        $listRows = $helper->getRows('SELECT id, name FROM #__ra_mail_lists ORDER BY id');

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
                $authorIds[] = $users[$key];
            }
            if (in_array('Mailman', $person['roles'], true)) {
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

    private function saveSubscription(int $listId, int $userId, int $recordType): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $helper = new ToolsHelper;
        $id = (int) $helper->getValue(
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

        $db->insertObject('#__ra_mail_subscriptions', (object) [
            'list_id' => $listId,
            'user_id' => $userId,
            'record_type' => $recordType,
            'method_id' => 2,
            'state' => 1,
            'ip_address' => '',
            'created' => $now,
            'created_by' => $actorId,
        ]);
    }
}
