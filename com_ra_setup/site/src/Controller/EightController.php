<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class EightController extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    public function previous()
    {
        $this->checkToken();
        $helper = new SetupHelper;
        $helper->assertCanRunWizard();
        $helper->assertWizardNotCompleted();
        $this->setRedirect(
            ComponentHelper::isEnabled('com_ra_mailman')
                ? 'index.php?option=com_ra_setup&view=seven'
                : 'index.php?option=com_ra_setup&view=five'
        );

        return true;
    }

    public function next()
    {
        $this->checkToken();
        $helper = new SetupHelper;
        $helper->assertCanRunWizard();
        $helper->assertWizardNotCompleted();
        $model = $this->getModel('Eight', 'Site');

        if (!$model) {
            throw new \RuntimeException('Unable to load the final confirmation model.', 500);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $emailProvisioned = false;
        $provisioning = null;
        $provisioningWarning = null;

        try {
            if (ComponentHelper::isEnabled('com_ra_mailman')) {
                $provisioning = $model->provisionEmailConfiguration();
                $emailProvisioned = true;
            }
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'email address already exists') !== false) {
                $emailProvisioned = true;
                $provisioningWarning = 'SMTP2GO could not create the sub-account because its sub-account email address already exists. '
                    . 'Complete or correct the SMTP2GO setup manually before sending email. Step 8 cannot be rerun; '
                    . 'a knowledgeable administrator can recover the setup interactively on the SMTP2GO website.';
            } else {
                $this->app->enqueueMessage($e->getMessage(), 'error');
                $this->setRedirect(
                    $model->isProvisioningGuardPresent()
                        ? Uri::root()
                        : 'index.php?option=com_ra_setup&view=eight'
                );

                return false;
            }
        }

        try {
            $db->transactionStart();
            $model->completeWizard();
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $message = $e->getMessage();

            if ($emailProvisioned) {
                $message .= ' Step 8 cannot be rerun. A knowledgeable administrator can recover the setup '
                    . 'interactively on the SMTP2GO website. If recovery is impractical, restore the cloned site '
                    . 'and run the configuration again.';
            }

            $this->app->enqueueMessage($message, 'error');
            $this->setRedirect($emailProvisioned ? Uri::root() : 'index.php?option=com_ra_setup&view=eight');

            return false;
        }

        $message = 'Configuration completed.';

        if (is_array($provisioning)) {
            $message .= ' SMTP2GO sub-account "' . $provisioning['subaccount_name']
                . '" (SMTP2GO ID ' . $provisioning['subaccount_id']
                . ') was provisioned using API-site record ' . (int) $provisioning['api_site_id'] . '.';
        }

        if (is_array($provisioning) && !empty($provisioning['instructions'])) {
            $message .= ' ' . $provisioning['instructions'];
        }

        $this->app->enqueueMessage($message, 'success');

        if ($provisioningWarning !== null) {
            $this->app->enqueueMessage($provisioningWarning, 'warning');
        }

        try {
            $webmasterWarning = $model->getWebmasterSuperUserWarning();

            if ($webmasterWarning !== null) {
                $this->app->enqueueMessage($webmasterWarning, 'warning');
            }
        } catch (\Throwable $e) {
            $this->app->enqueueMessage(
                'Unable to verify whether the Webmaster account is an enabled Joomla Super User; please check it manually.',
                'warning'
            );
        }

        $this->setRedirect(Uri::root());

        return true;
    }

    public function cancel()
    {
        $this->checkToken();
        $helper = new SetupHelper;
        $helper->assertCanRunWizard();
        $helper->assertWizardNotCompleted();
        $this->setRedirect(Uri::root());

        return true;
    }
}
