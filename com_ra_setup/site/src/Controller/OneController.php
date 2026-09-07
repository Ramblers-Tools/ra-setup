<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class OneController extends BaseController {

    public function display($cachable = false, $urlparams = false) {
        return parent::display($cachable, $urlparams);
    }

    public function update() {
        $this->checkToken();

        $helper = new SetupHelper;
        $helper->assertCanRunWizard();

        $model = $this->getModel('One', 'Site');

        if (!$model) {
            throw new \RuntimeException('Unable to load the setup model.', 500);
        }

        $data = $this->input->get('jform', [], 'array');
        $form = $model->getForm();

        if (!$form) {
            throw new \RuntimeException('Unable to load the setup form.', 500);
        }

        $isArea = ($data['area_group'] ?? 'G') === 'A';
        $form->removeField($isArea ? 'group_code' : 'area_code');

        $data = $model->validate($form, $data);

        if ($data === false) {
            foreach (array_slice($model->getErrors(), 0, 3) as $error) {
                $message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
                $this->app->enqueueMessage($message, 'warning');
            }

            $this->app->setUserState(
                    'com_ra_setup.one.data',
                    $this->input->get('jform', [], 'array')
            );
            $this->setRedirect('index.php?option=com_ra_setup&view=one');

            return false;
        }

        $homeCode = strtoupper(trim($data[$isArea ? 'area_code' : 'group_code'] ?? ''));
        $nearest = $helper->getNearestOrganisations($homeCode, 5, 'N');

        if ($nearest === false) {
            $this->app->setUserState('com_ra_setup.one.data', $data);
            $this->app->enqueueMessage('Unable to update the setup details.', 'error');
            $this->setRedirect('index.php?option=com_ra_setup&view=one');

            return false;
        }

        $groupList = implode(',', array_map(
                        static fn($organisation) => $organisation->code,
                        $nearest
        ));

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $db->transactionStart();

            if (!$helper->updateComponentParams('com_ra_tools', [
                        'default_group' => $homeCode,
                        'group_list' => $groupList,
                    ])) {
                throw new \RuntimeException('Unable to update RA Tools parameters.');
            }

            if (!$helper->updateModuleParams('mod_raheader', [
                        'website_title' => $data['site_name'] ?? '',
                        'website_subtitle' => $data['strapline'] ?? '',
                    ])) {
                throw new \RuntimeException('Unable to update RA Header parameters.');
            }
            $helper->updateMaillists($homeCode);
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->app->setUserState('com_ra_setup.one.data', $data);
            $this->app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect('index.php?option=com_ra_setup&view=one');

            return false;
        }

        $this->app->setUserState('com_ra_setup.one.data', null);
        $this->app->enqueueMessage(Text::_('Setup details updated.'), 'success');
        $this->setRedirect('index.php?option=com_ra_setup&view=two');

        return true;
    }

}
