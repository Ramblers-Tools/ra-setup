<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class FiveController extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    public function update()
    {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $model = $this->getModel('Five', 'Site');
        $submittedData = $this->input->get('jform', [], 'array');
        $form = $model ? $model->getForm() : null;

        if (!$form) {
            throw new \RuntimeException('Unable to load the Step 5 form.', 500);
        }

        $validated = $model->validate($form, $submittedData);

        if ($validated === false) {
            foreach (array_slice($model->getErrors(), 0, 3) as $error) {
                $this->app->enqueueMessage(
                    $error instanceof \Throwable ? $error->getMessage() : (string) $error,
                    'warning'
                );
            }

            return $this->returnToForm($submittedData);
        }

        try {
            $data = $model->sanitise($validated);
        } catch (\InvalidArgumentException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');

            return $this->returnToForm($submittedData);
        }

        $this->app->setUserState('com_ra_setup.five.data', $data);
        $this->app->setUserState('com_ra_setup.six.form', null);
        $this->setRedirect('index.php?option=com_ra_setup&view=six');

        return true;
    }

    private function returnToForm(array $data): bool
    {
        $this->app->setUserState('com_ra_setup.five.data', $data);
        $this->setRedirect('index.php?option=com_ra_setup&view=five');

        return false;
    }
}
