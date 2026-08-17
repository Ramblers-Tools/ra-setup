<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;

class ThreeModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.three',
            'three',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    public function updateWalkMenuItems(string $mode): void
    {
        $states = [
            'home' => ['radius' => 0, 'groups' => 0],
            'radius' => ['radius' => 1, 'groups' => 0],
            'neighbours' => ['radius' => 0, 'groups' => 1],
        ];

        if (!isset($states[$mode])) {
            throw new \InvalidArgumentException('Unknown walks selection mode.');
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($states[$mode] as $note => $published) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('published') . ' = ' . $published)
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('note') . ' = ' . $db->quote($note));
                if (JDEBUG){
                    Factory::getApplication()->enqueueMessage($query->__toString(), 'info');
                }
            $db->setQuery($query)->execute();
        }
    }

    protected function loadFormData()
    {
        return Factory::getApplication()->getUserState('com_ra_setup.three.data', []);
    }
}
