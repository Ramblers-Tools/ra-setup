<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;

class OneModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.one',
            'one',
            [
                'control' => 'jform',
                'load_data' => $loadData,
            ]
        );
    }

    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ra_setup.one.data', []);

        if (empty($data)) {
            $data = ComponentHelper::getParams('com_ra_tools')->toArray();
            $data['site_name'] = $app->get('sitename', '');
        }

        return $data;
    }
}
