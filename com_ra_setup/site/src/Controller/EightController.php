<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
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

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $db->transactionStart();
            $model->completeWizard();
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect('index.php?option=com_ra_setup&view=eight');

            return false;
        }

        $this->app->enqueueMessage('Configuration completed', 'success');
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
