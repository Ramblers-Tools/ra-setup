<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Ramblers\Component\Ra_delivery\Site\Helper\SmtpHelper;
use Ramblers\Component\Ra_delivery\Site\Service\Smtp2goException;
use Ramblers\Component\Ra_setup\Site\Service\SenderRegistrationValidator;

class EightModel extends BaseDatabaseModel
{
    private const EMAIL_LIMITS = [
        2000, 5000, 10000, 20000, 30000, 40000, 50000,
    ];

    public function provisionEmailConfiguration(): array
    {
        $deliveryParams = ComponentHelper::getParams('com_ra_delivery');
        $subaccountName = trim((string) $deliveryParams->get('smtp2go_subaccount_name', ''));
        $apiSiteId = (int) $deliveryParams->get('smtp2go_api_site_id', 0);

        if ($subaccountName === '') {
            throw new \RuntimeException('Complete Step 7 before confirming the email configuration.');
        }

        $configuredLimit = ComponentHelper::getParams('com_ra_setup')->get('email_limit', 10000);
        $emailLimit = trim((string) $configuredLimit) === '' ? 10000 : (int) $configuredLimit;

        if (!in_array($emailLimit, self::EMAIL_LIMITS, true)) {
            throw new \RuntimeException(
                'RA Setup email_limit is ' . var_export($configuredLimit, true)
                    . '. Select one of: ' . implode(', ', self::EMAIL_LIMITS) . '.'
            );
        }

        $webmaster = $this->getWebmaster();
        $validator = new SenderRegistrationValidator();
        $registration = $validator->validate(
            (string) $deliveryParams->get('smtp2go_sender_registration', ''),
            (string) $deliveryParams->get('sender_email', ''),
            (string) ComponentHelper::getParams('com_ra_tools')->get('website', '')
        );

        Factory::getApplication()->bootComponent('com_ra_delivery');
        $smtp = new SmtpHelper();

        try {
            $smtp->validateConfiguredApiSite();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'The RA Delivery SMTP2GO configuration is invalid: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        // This single primary-key insert is the atomic one-shot boundary. All
        // local validation above it is repeatable; no provider mutation has run.
        $this->acquireProvisioningGuard();

        $subaccountId = '';
        $apiKey = '';
        $senderDomainAttempted = false;
        $singleSenderAttempted = false;
        $stage = 'creating the SMTP2GO sub-account';

        try {
            $subaccount = $smtp->createSubaccount($subaccountName, $webmaster->email, $emailLimit);
            $subaccountId = trim((string) $subaccount['id']);
            $stage = 'creating the sub-account API key';
            $key = $smtp->createSubaccountApiKey(
                $subaccountId,
                'RA Delivery - ' . $subaccountName
            );
            $apiKey = trim((string) $key['api_key']);
            $registrationDetails = [
                'choice' => $registration['choice'],
            ];

            if (in_array(
                $registration['choice'],
                [SenderRegistrationValidator::SENDER_DOMAIN, SenderRegistrationValidator::BOTH],
                true
            )) {
                $stage = 'registering the sender domain';
                $senderDomainAttempted = true;
                $registrationDetails['sender_domain_setup'] = $smtp->registerSenderDomain(
                    $subaccountId,
                    $registration['sender_domain']
                );
            }

            if (in_array(
                $registration['choice'],
                [SenderRegistrationValidator::SINGLE_EMAIL, SenderRegistrationValidator::BOTH],
                true
            )) {
                $stage = 'registering the single sender email';
                $singleSenderAttempted = true;
                $registrationDetails['single_sender_email'] = $smtp->registerSingleSenderEmail(
                    $subaccountId,
                    $registration['sender_email']
                );
            }

            $stage = 'saving the local RA Delivery configuration';
            $this->persistProvisioningResult(
                $smtp,
                $subaccountName,
                $subaccountId,
                $apiKey,
                $registrationDetails
            );
        } catch (\Throwable $e) {
            $this->compensateProvisioning(
                $smtp,
                $subaccountName,
                $subaccountId,
                $apiKey,
                $registration,
                $senderDomainAttempted,
                $singleSenderAttempted
            );
            $message = $this->formatProvisioningFailure($e, $stage)
                . ' Step 8 cannot be rerun. A knowledgeable administrator can recover the setup '
                . 'interactively on the SMTP2GO website, or the cloned site can be restored and configured again.';
            Log::add($message, Log::ERROR, 'ra_setup');
            throw new \RuntimeException($message, (int) $e->getCode(), $e);
        }

        return [
            'subaccount_name' => $subaccountName,
            'subaccount_id' => $subaccountId,
            'api_site_id' => $apiSiteId,
            'registration' => $registration,
            'instructions' => $validator->getInstructions($registration, true),
        ];
    }

    public function completeWizard(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $homeId = $this->getHomeArticleId();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('featured') . ' = 0');
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('featured') . ' = 1')
            ->set($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('id') . ' = ' . $homeId);
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__content_frontpage'));
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__content_frontpage'))
            ->columns([
                $db->quoteName('content_id'),
                $db->quoteName('ordering'),
                $db->quoteName('featured_up'),
                $db->quoteName('featured_down'),
            ])
            ->values($homeId . ', 0, NULL, NULL');
        $db->setQuery($query)->execute();

        $completedAt = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ra_control'))
            ->where($db->quoteName('record_type') . ' = 3');
        $exists = (int) $db->setQuery($query)->loadResult() > 0;

        if ($exists) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ra_control'))
                ->set($db->quoteName('key_value') . ' = ' . $db->quote($completedAt))
                ->where($db->quoteName('record_type') . ' = 3');
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ra_control'))
                ->columns([
                    $db->quoteName('record_type'),
                    $db->quoteName('key_value'),
                ])
                ->values('3, ' . $db->quote($completedAt));
        }

        $db->setQuery($query)->execute();
    }

    public function isProvisioningGuardPresent(): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ra_control'))
            ->where($db->quoteName('record_type') . ' = 3');

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    private function acquireProvisioningGuard(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $startedAt = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__ra_control'))
            ->columns([
                $db->quoteName('record_type'),
                $db->quoteName('key_value'),
            ])
            ->values('3, ' . $db->quote('SMTP2GO provisioning started ' . $startedAt));

        try {
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            $exists = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ra_control'))
                    ->where($db->quoteName('record_type') . ' = 3')
            )->loadResult() > 0;

            if ($exists) {
                throw new \RuntimeException(
                    'SMTP2GO provisioning has already been started or completed; Step 8 cannot be rerun.',
                    409,
                    $e
                );
            }

            throw $e;
        }
    }

    private function getWebmaster(): object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . implode(', ', [
                $db->quoteName('u.id'),
                $db->quoteName('u.email'),
                $db->quoteName('u.name'),
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

        if (filter_var(trim((string) $webmasters[0]->email), FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('The Webmaster user does not have a valid email address.');
        }

        return $webmasters[0];
    }

    public function getWebmasterSuperUserWarning(): ?string
    {
        $webmaster = $this->getWebmaster();
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
            ->where($db->quoteName('u.id') . ' = ' . (int) $webmaster->id)
            ->where($db->quoteName('u.block') . ' = 0')
            ->where($db->quoteName('g.title') . ' = ' . $db->quote('Super Users'));

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            return null;
        }

        return 'The Webmaster account for ' . $webmaster->name
            . ' is not an enabled Joomla Super User. Please update it manually.';
    }

    private function persistProvisioningResult(
        SmtpHelper $smtp,
        string $subaccountName,
        string $subaccountId,
        string $apiKey,
        array $registrationDetails
    ): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $message = json_encode(
            ['sender_registration' => $registrationDetails],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        $db->transactionStart();

        try {
            $smtp->replaceApiSiteCredential($subaccountName, $subaccountId, $apiKey);
            $this->activateDeliveryConfiguration($subaccountId);
            $log = (object) [
                'log_date' => Factory::getDate()->toSql(),
                'sub_system' => 'RA Setup',
                'record_type' => '1',
                'ref' => substr($subaccountId, 0, 10),
                'message' => $message,
            ];
            $db->insertObject('#__ra_logfile', $log);
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }
    }

    private function activateDeliveryConfiguration(string $subaccountId): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_ra_delivery'));
        $encoded = $db->setQuery($query)->loadResult();

        if ($encoded === null) {
            throw new \RuntimeException('The RA Delivery component configuration could not be found.');
        }

        $params = new Registry((string) $encoded);
        $params->set('smtp2go_subaccount_id', $subaccountId);
        $params->remove('smtp2go_subaccount_name');
        $params->remove('Subaccount_filter');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_ra_delivery'));
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_ra_delivery'));
        $stored = new Registry((string) $db->setQuery($query)->loadResult());

        if ((string) $stored->get('smtp2go_subaccount_id', '') !== $subaccountId
            || $stored->get('smtp2go_subaccount_name', null) !== null
        ) {
            throw new \RuntimeException('The RA Delivery sub-account configuration could not be verified.');
        }
    }

    private function compensateProvisioning(
        SmtpHelper $smtp,
        string $subaccountName,
        string $subaccountId,
        string $apiKey,
        array $registration,
        bool $senderDomainAttempted,
        bool $singleSenderAttempted
    ): void {
        $operations = [];

        if ($singleSenderAttempted) {
            $operations['remove single sender email'] = static function () use ($smtp, $subaccountId, $registration): void {
                $smtp->removeSingleSenderEmail($subaccountId, $registration['sender_email']);
            };
        }

        if ($senderDomainAttempted) {
            $operations['remove sender domain'] = static function () use ($smtp, $subaccountId, $registration): void {
                $smtp->removeSenderDomain($subaccountId, $registration['sender_domain']);
            };
        }

        if ($apiKey !== '') {
            $operations['remove sub-account API key'] = static function () use ($smtp, $subaccountId, $apiKey): void {
                $smtp->removeSubaccountApiKey($subaccountId, $apiKey);
            };
        }

        if ($subaccountId !== '') {
            $operations['close sub-account'] = static function () use ($smtp, $subaccountId): void {
                $smtp->closeSubaccount($subaccountId);
            };
        }

        foreach ($operations as $operation => $callback) {
            try {
                $callback();
                Log::add(
                    'SMTP2GO cleanup succeeded: ' . $operation . ' for sub-account "'
                        . $subaccountName . '" (' . $subaccountId . ').',
                    Log::INFO,
                    'ra_setup'
                );
            } catch (\Throwable $cleanupError) {
                $cleanupMessage = $cleanupError->getMessage();

                if ($apiKey !== '') {
                    $cleanupMessage = str_replace($apiKey, '[redacted API key]', $cleanupMessage);
                }

                Log::add(
                    'SMTP2GO cleanup failed: ' . $operation . ' for sub-account "'
                        . $subaccountName . '" (' . $subaccountId . '): ' . $cleanupMessage,
                    Log::ERROR,
                    'ra_setup'
                );
            }
        }
    }

    private function formatProvisioningFailure(\Throwable $error, string $stage): string
    {
        if ($stage === 'saving the local RA Delivery configuration') {
            return 'Local SMTP2GO configuration persistence failed while ' . $stage . ': '
                . $error->getMessage();
        }

        if ($error instanceof Smtp2goException) {
            if ($error->getHttpStatus() === 429) {
                return 'The SMTP2GO rate limit was reached while ' . $stage . ': ' . $error->getMessage();
            }

            return 'SMTP2GO provisioning failed while ' . $stage . ': ' . $error->getMessage();
        }

        return 'Email provisioning failed while ' . $stage . ': ' . $error->getMessage();
    }

    private function getHomeArticleId(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote('home'))
            ->order($db->quoteName('id') . ' ASC');
        $homeId = (int) $db->setQuery($query, 0, 1)->loadResult();

        if ($homeId < 1) {
            throw new \RuntimeException('Unable to find the Home article.');
        }

        return $homeId;
    }
}
