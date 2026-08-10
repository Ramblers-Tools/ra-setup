<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class FourController extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    public function update()
    {
        $this->checkToken();

        (new SetupHelper)->assertCanRunWizard();
        $model = $this->getModel('Four', 'Site');

        if (!$model) {
            throw new \RuntimeException('Unable to load the Step 4 model.', 500);
        }

        $submittedData = $this->input->get('jform', [], 'array');
        $form = $model->getForm();

        if (!$form) {
            throw new \RuntimeException('Unable to load the Step 4 form.', 500);
        }

        $data = $model->validate($form, $submittedData);

        if ($data === false) {
            foreach (array_slice($model->getErrors(), 0, 3) as $error) {
                $message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
                $this->app->enqueueMessage($message, 'warning');
            }

            return $this->returnToForm($submittedData);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $db->transactionStart();
            $missingExtensions = $model->updateExtensionGroups($data);
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->app->enqueueMessage($e->getMessage(), 'error');

            return $this->returnToForm($submittedData);
        }

        if (!empty($missingExtensions)) {
            $this->app->enqueueMessage(
                'The following modules or plugins are not installed and could not be enabled: '
                . implode(', ', $missingExtensions),
                'warning'
            );
        }

        $this->app->setUserState('com_ra_setup.four.data', null);
        $this->app->enqueueMessage('Optional component selection updated.', 'success');
        $this->setRedirect('index.php?option=com_ra_setup&view=five');

        return true;
    }

    private function returnToForm(array $data): bool
    {
        $this->app->setUserState('com_ra_setup.four.data', $data);
        $this->setRedirect('index.php?option=com_ra_setup&view=four');

        return false;
    }
}
