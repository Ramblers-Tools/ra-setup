<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class SevenController extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    public function previous()
    {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $this->app->setUserState(
            'com_ra_setup.seven.data',
            $this->input->get('jform', [], 'array')
        );
        $this->setRedirect('index.php?option=com_ra_setup&view=five');

        return true;
    }

    public function update()
    {
        $this->checkToken();
        $helper = new SetupHelper;
        $helper->assertCanRunWizard();
        $model = $this->getModel('Seven', 'Site');

        if (!$model || !$model->isMailmanEnabled()) {
            throw new \RuntimeException('Step 7 is only available when RA Mailman is enabled.', 404);
        }

        $submittedData = $this->input->get('jform', [], 'array');
        $form = $model->getForm();

        if (!$form) {
            throw new \RuntimeException('Unable to load the Step 7 form.', 500);
        }

        $data = $model->validate($form, $submittedData);

        if ($data === false) {
            foreach (array_slice($model->getErrors(), 0, 3) as $error) {
                $this->app->enqueueMessage(
                    $error instanceof \Throwable ? $error->getMessage() : (string) $error,
                    'warning'
                );
            }

            return $this->returnToForm($submittedData);
        }

        try {
            $configuration = $model->validateConfiguration($data);
        } catch (\InvalidArgumentException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');

            return $this->returnToForm($submittedData);
        } catch (\Throwable $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');

            return $this->returnToForm($submittedData);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $db->transactionStart();

            if (!$helper->updateComponentParams('com_ra_delivery', [
                'smtp2go_subaccount_name' => $configuration['sub_account'],
                'notify_user' => $configuration['notify_user'],
                'smtp2go_sender_registration' => $configuration['sender_registration'],
            ])) {
                throw new \RuntimeException('Unable to update RA Delivery parameters.');
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->app->enqueueMessage($e->getMessage(), 'error');

            return $this->returnToForm($submittedData);
        }

        $this->app->setUserState('com_ra_setup.seven.data', null);
        $this->app->enqueueMessage(
            'SMTP2GO sub-account name "' . $configuration['sub_account']
                . '" is available and the email configuration was saved.',
            'success'
        );
        $this->app->enqueueMessage($configuration['instructions'], 'message');
        $this->setRedirect('index.php?option=com_ra_setup&view=eight');

        return true;
    }

    private function returnToForm(array $data): bool
    {
        $this->app->setUserState('com_ra_setup.seven.data', $data);
        $this->setRedirect('index.php?option=com_ra_setup&view=seven');

        return false;
    }
}
