<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class SixController extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    public function previous()
    {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $submitted = $this->input->get('jform', [], 'array');
        $submittedPeople = isset($submitted['people']) && is_array($submitted['people'])
            ? $submitted['people']
            : [];

        $this->app->setUserState('com_ra_setup.six.form', $submittedPeople);
        $this->setRedirect('index.php?option=com_ra_setup&view=five');

        return true;
    }

    public function update()
    {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $model = $this->getModel('Six', 'Site');

        if (!$model) {
            throw new \RuntimeException('Unable to load the Step 6 model.', 500);
        }

        $submitted = $this->input->get('jform', [], 'array');
        $submittedPeople = isset($submitted['people']) && is_array($submitted['people'])
            ? $submitted['people']
            : [];

        try {
            $people = $model->validatePeople($submittedPeople);
        } catch (\InvalidArgumentException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');
            $this->app->setUserState('com_ra_setup.six.form', $submittedPeople);
            $this->setRedirect('index.php?option=com_ra_setup&view=six');

            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $db->transactionStart();
            $warnings = $model->persistPeople($people);
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->app->enqueueMessage($e->getMessage(), 'error');
            $this->app->setUserState('com_ra_setup.six.form', $submittedPeople);
            $this->setRedirect('index.php?option=com_ra_setup&view=six');

            return false;
        }

        foreach ($warnings as $warning) {
            $this->app->enqueueMessage($warning, 'warning');
        }

        $this->app->setUserState('com_ra_setup.six.form', null);
        $this->app->setUserState('com_ra_setup.six.data', $people);
        $this->app->enqueueMessage('Committee users, profiles, contacts and permissions updated.', 'success');
        $this->setRedirect('index.php?option=com_ra_setup&view=seven');

        return true;
    }
}
