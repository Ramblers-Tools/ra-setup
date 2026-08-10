<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

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
            $params = ComponentHelper::getParams('com_ra_tools');
            $defaultCode = strtoupper(trim((string) $params->get('default_group', '')));
            $data = $params->toArray();
            $data['area_group'] = strlen($defaultCode) === 2 ? 'A' : 'G';
            $data['area_code'] = strlen($defaultCode) === 2 ? $defaultCode : '';
            $data['group_code'] = strlen($defaultCode) === 4 ? $defaultCode : '';
            $data['site_name'] = $app->get('sitename', '');
            $data['strapline'] = $this->getHeaderStrapline();
        }

        return $data;
    }

    private function getHeaderStrapline(): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_raheader'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->order($db->quoteName('published') . ' DESC')
            ->order($db->quoteName('ordering') . ' ASC')
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($query, 0, 1);
        $params = $db->loadResult();

        if (!is_string($params) || $params === '') {
            return '';
        }

        return trim((string) (new Registry($params))->get('website_subtitle', ''));
    }
}
