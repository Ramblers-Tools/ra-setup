<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class TwoController extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    public function update()
    {
        $this->checkToken();

        $helper = new SetupHelper;
        $helper->assertCanRunWizard();

        $model = $this->getModel('Two', 'Site');

        if (!$model) {
            throw new \RuntimeException('Unable to load the Step 2 model.', 500);
        }

        $submittedData = $this->input->get('jform', [], 'array');
        $form = $model->getForm();

        if (!$form) {
            throw new \RuntimeException('Unable to load the Step 2 form.', 500);
        }

        if (($submittedData['facebook'] ?? '0') !== '1') {
            $form->removeField('facebook_link');
        }

        $data = $model->validate($form, $submittedData);

        if ($data === false) {
            foreach (array_slice($model->getErrors(), 0, 3) as $error) {
                $message = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
                $this->app->enqueueMessage($message, 'warning');
            }

            return $this->returnToForm($submittedData);
        }

        if (($submittedData['facebook'] ?? '0') === '1' && trim($data['facebook_link'] ?? '') === '') {
            $this->app->enqueueMessage('The Facebook link is required when a link is requested.', 'warning');

            return $this->returnToForm($submittedData);
        }

        $data['title'] = trim($data['title'] ?? '');
        $bodyText = html_entity_decode(strip_tags($data['body'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $bodyText = trim(str_replace("\xc2\xa0", '', $bodyText));

        if ($data['title'] === '' || $bodyText === '') {
            if ($data['title'] === '') {
                $this->app->enqueueMessage('The Home page title is required.', 'warning');
            }

            if ($bodyText === '') {
                $this->app->enqueueMessage('The site description is required.', 'warning');
            }

            return $this->returnToForm($submittedData);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $db->transactionStart();
            $model->updateHomeArticle($data['title'], $data['body']);

            if (!$helper->updateComponentParams('com_ra_tools', ['website' => Uri::root()])) {
                throw new \RuntimeException('Unable to update the RA Tools website parameter.');
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->app->enqueueMessage($e->getMessage(), 'error');

            return $this->returnToForm($submittedData);
        }

        $this->app->setUserState('com_ra_setup.two.data', null);
        $this->app->enqueueMessage('Home page details updated.', 'success');
        $this->setRedirect('index.php?option=com_ra_setup&view=three');

        return true;
    }

    private function returnToForm(array $data): bool
    {
        $this->app->setUserState('com_ra_setup.two.data', $data);
        $this->setRedirect('index.php?option=com_ra_setup&view=two');

        return false;
    }
}
