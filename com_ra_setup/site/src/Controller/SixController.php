<?php

/*
 * 5/08/26 CB define $this-db
 */

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class SixController extends FormController {

    protected $db;

    public function __construct(
            $config = [],
            ?MVCFactoryInterface $factory = null,
            ?CMSWebApplicationInterface $app = null,
            ?Input $input = null,
            ?FormFactoryInterface $formFactory = null
    ) {
        parent::__construct($config, $factory, $app, $input, $formFactory);
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    public function display($cachable = false, $urlparams = false) {

        return parent::display($cachable, $urlparams);
    }

    public function previous() {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $submitted = $this->input->get('jform', [], 'array');
        $submittedPeople = isset($submitted['people']) && is_array($submitted['people']) ? $submitted['people'] : [];

        $this->app->setUserState('com_ra_setup.six.form', $submittedPeople);
        $this->setRedirect('index.php?option=com_ra_setup&view=five');

        return true;
    }

    public function update() {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $model = $this->getModel('Six', 'Site');

        if (!$model) {
            throw new \RuntimeException('Unable to load the Step 6 model.', 500);
        }

        $submitted = $this->input->get('jform', [], 'array');
        $submittedPeople = isset($submitted['people']) && is_array($submitted['people']) ? $submitted['people'] : [];

        try {
            $people = $model->validatePeople($submittedPeople);
        } catch (\InvalidArgumentException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');
            $this->app->setUserState('com_ra_setup.six.form', $submittedPeople);
            $this->setRedirect('index.php?option=com_ra_setup&view=six');

            return false;
        }

        try {
            $this->db->transactionStart();
            $warnings = $model->persistPeople($people);
            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();
            $this->app->enqueueMessage('persisting people ' . $e->getMessage(), 'error');
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

        if (ComponentHelper::isEnabled('com_ra_mailman')) {
            $this->setRedirect('index.php?option=com_ra_setup&view=seven');
        } elseif (!(new SetupHelper)->isWizardCompleted()) {
            $this->setRedirect('index.php?option=com_ra_setup&view=eight');
        } else {
            $this->setRedirect('index.php');
        }

        return true;
    }

}
