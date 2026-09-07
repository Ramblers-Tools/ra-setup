<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Ramblers\Component\Ra_delivery\Site\Helper\SmtpHelper;
use Ramblers\Component\Ra_setup\Site\Service\SenderRegistrationValidator;

class SevenModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.seven',
            'seven',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    public function isMailmanEnabled(): bool
    {
        return ComponentHelper::isEnabled('com_ra_mailman');
    }

    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $saved = $app->getUserState('com_ra_setup.seven.data', []);
        $params = ComponentHelper::getParams('com_ra_delivery');

        $subaccountName = trim((string) $params->get('smtp2go_subaccount_name', ''));

        if ($subaccountName === '') {
            $subaccountName = $this->getHeaderSiteName();
        }

        $data = [
            'sub_account' => $subaccountName,
            'contact_id' => (int) $params->get('notify_user', 0),
            'sender_registration' => (string) $params->get('smtp2go_sender_registration', 'domain'),
        ];

        return empty($saved) ? $data : array_replace($data, $saved);
    }

    public function validateConfiguration(array $data): array
    {
        $submittedName = (string) ($data['sub_account'] ?? '');

        if (!preg_match('//u', $submittedName)) {
            throw new \InvalidArgumentException('The sub-account name must contain valid UTF-8 characters.');
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', $submittedName));

        if ($name === '') {
            throw new \InvalidArgumentException('Enter a sub-account name.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new \InvalidArgumentException('The sub-account name must not contain control characters.');
        }

        if (strlen($name) > 100) {
            throw new \InvalidArgumentException('The sub-account name must not exceed 100 characters.');
        }

        if (!preg_match("/^[A-Za-z0-9][A-Za-z0-9 ._()&'\\-]*$/", $name)) {
            throw new \InvalidArgumentException(
                'The sub-account name must start with a letter or number and may contain only letters, numbers, '
                    . 'spaces, periods, underscores, parentheses, ampersands, apostrophes, and hyphens.'
            );
        }

        $notifyUserId = (int) ($data['contact_id'] ?? 0);

        if ($notifyUserId < 1) {
            throw new \InvalidArgumentException('Select a user to receive delivery exceptions.');
        }

        $this->loadEnabledUser($notifyUserId, 'The selected delivery-notification user');
        $webmaster = $this->getWebmaster();
        $validator = new SenderRegistrationValidator();
        $registration = $validator->validate(
            (string) ($data['sender_registration'] ?? ''),
            (string) ComponentHelper::getParams('com_ra_delivery')->get('sender_email', ''),
            (string) ComponentHelper::getParams('com_ra_tools')->get('website', '')
        );

        // Booting the component registers its namespace before the helper is resolved.
        Factory::getApplication()->bootComponent('com_ra_delivery');
        $match = (new SmtpHelper())->findSubaccount($name);

        if ($match !== null) {
            $message = 'Unable to register sub-account ' . $name . ' as it already exists';
            Log::add($message, Log::WARNING, 'ra_setup');
            throw new \RuntimeException($message);
        }

        return [
            'sub_account' => $name,
            'notify_user' => $notifyUserId,
            'webmaster_user_id' => (int) $webmaster->id,
            'sender_registration' => $registration['choice'],
            'instructions' => $validator->getInstructions($registration, false),
        ];
    }

    public function getWebmaster(): object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . implode(', ', [
                $db->quoteName('u.id'),
                $db->quoteName('u.email'),
            ]))
            ->from($db->quoteName('#__users', 'u'))
            ->innerJoin(
                $db->quoteName('#__contact_details', 'c')
                . ' ON ' . $db->quoteName('c.user_id') . ' = ' . $db->quoteName('u.id')
            )
            ->innerJoin(
                $db->quoteName('#__categories', 'cat')
                . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('c.catid')
            )
            ->where($db->quoteName('u.block') . ' = 0')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('cat.extension') . ' = ' . $db->quote('com_contact'))
            ->where('LOWER(' . $db->quoteName('cat.title') . ') = ' . $db->quote('committee'))
            ->where('LOWER(' . $db->quoteName('c.con_position') . ') LIKE ' . $db->quote('%webmaster%'));
        $webmasters = $db->setQuery($query)->loadObjectList();

        if (count($webmasters) !== 1) {
            throw new \RuntimeException('Exactly one enabled Webmaster user must be recorded in Steps 5 and 6.');
        }

        return $this->validateUserEmail($webmasters[0], 'The Webmaster user');
    }

    private function getHeaderSiteName(): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_raheader'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->order($db->quoteName('published') . ' DESC')
            ->order($db->quoteName('ordering') . ' ASC')
            ->order($db->quoteName('id') . ' ASC');
        $params = $db->setQuery($query, 0, 1)->loadResult();

        if (!is_string($params) || $params === '') {
            return '';
        }

        return trim((string) (new Registry($params))->get('website_title', ''));
    }

    private function loadEnabledUser(int $userId, string $label): object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('email')])
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' = ' . $userId)
            ->where($db->quoteName('block') . ' = 0');
        $user = $db->setQuery($query)->loadObject();

        if ($user === null) {
            throw new \InvalidArgumentException($label . ' does not exist or is disabled.');
        }

        return $this->validateUserEmail($user, $label);
    }

    private function validateUserEmail(object $user, string $label): object
    {
        if (filter_var(trim((string) $user->email), FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException($label . ' does not have a valid email address.');
        }

        return $user;
    }
}
