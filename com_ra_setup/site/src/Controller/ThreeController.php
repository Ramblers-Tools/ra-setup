<?php
/*
* 07/09/26 CB restrict group list to specified number of nearby groups
*/
namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class ThreeController extends BaseController
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
            'com_ra_setup.three.data',
            $this->input->get('jform', [], 'array')
        );
        $this->setRedirect('index.php?option=com_ra_setup&view=two');

        return true;
    }

    public function update()
    {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $model = $this->getModel('Three', 'Site');
        $submittedData = $this->input->get('jform', [], 'array');
        $form = $model ? $model->getForm() : null;

        if (!$form) {
            throw new \RuntimeException('Unable to load the Step 3 form.', 500);
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
            $mode = $this->getSelectionMode($data);
            $neighbour_count =  (int) ($data['neighbour_count'] ?? 0);
        } catch (\InvalidArgumentException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');

            return $this->returnToForm($submittedData);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $db->transactionStart();
            $model->updateWalkMenuItems($mode, $neighbour_count);
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->app->enqueueMessage($e->getMessage(), 'error');

            return $this->returnToForm($submittedData);
        }

        $this->app->setUserState('com_ra_setup.three.data', null);
        $this->app->enqueueMessage('Walk programme menu entries updated.', 'success');
        $this->setRedirect('index.php?option=com_ra_setup&view=four');

        return true;
    }

    private function getSelectionMode(array $data): string
    {
        $scope = (string) ($data['walks_scope'] ?? '');

        if ($scope === 'home') {
            return 'home';
        }

        if ($scope !== 'other') {
            throw new \InvalidArgumentException('Select which walks should be shown.');
        }

        $method = (string) ($data['selection_method'] ?? '');

        if ($method === 'distance') {
            $radius = (int) ($data['radius'] ?? 0);

            if ($radius < 10 || $radius > 100) {
                throw new \InvalidArgumentException('The radius must be between 10 and 100 miles.');
            }

            return 'radius';
        }

        if ($method === 'neighbours') {
            $count = (int) ($data['neighbour_count'] ?? 0);

            if ($count < 1 || $count > 6) {
                throw new \InvalidArgumentException('Select between 1 and 6 neighbouring groups.');
            }

            return 'neighbours';
        }

        throw new \InvalidArgumentException('Select how other groups should be chosen.');
    }

    private function returnToForm(array $data): bool
    {
        $this->app->setUserState('com_ra_setup.three.data', $data);
        $this->setRedirect('index.php?option=com_ra_setup&view=three');

        return false;
    }
}
