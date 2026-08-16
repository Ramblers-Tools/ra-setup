<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class SevenModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.seven',
            'seven',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    public function isMailmanEnabled(): bool
    {
        return ComponentHelper::isEnabled('com_ra_mailman');
    }

    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $saved = $app->getUserState('com_ra_setup.seven.data', []);
        $params = ComponentHelper::getParams('com_ra_delivery');
        $domain = trim((string) $params->get('subdomain', ''));

        if ($domain === '') {
            $domain = $this->firstCharacters($this->getHeaderSiteName(), 12);
        }

        $data = [
            'domain' => $domain,
            'contact_id' => (int) $params->get('contact_id', 0),
        ];

        return empty($saved) ? $data : array_replace($data, $saved);
    }

    private function getHeaderSiteName(): string
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

        return trim((string) (new Registry($params))->get('website_title', ''));
    }

    private function firstCharacters(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}
