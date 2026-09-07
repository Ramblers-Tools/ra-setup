<?php
/*
* 07/09/26 CB restrict group list to specified number of nearby groups
*/

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

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

    public function updateWalkMenuItems(string $mode, int $neighbour_count = 0): void
    {

        $toolsHelper = new ToolsHelper();
        $sql = 'UPDATE `#__menu` SET `published` = 0 WHERE `alias` ="walks-s" OR `alias` ="walks-g" OR `alias` ="walks-r"';
        $toolsHelper->executeCommand($sql);
        if ($mode == 'home'){
            $sql = 'UPDATE `#__menu` SET `published`=1 WHERE `alias` ="walks-s"';           
        } elseif ($mode == 'radius') {
            $sql = 'UPDATE `#__menu` SET `published`=1 WHERE `alias` ="walks-r"';
        } elseif ($mode == 'neighbours') {
            $sql = 'UPDATE `#__menu` SET `published`=1 WHERE `alias` ="walks-g"';
        } else {
            throw new \InvalidArgumentException('Unknown walks selection mode.');
        }

        if (JDEBUG){
            Factory::getApplication()->enqueueMessage($sql, 'info');
        }$toolsHelper->executeCommand($sql);
        // Update the group list in the config parameters (regardless of which mode was selected)
        $helper = new SetupHelper;
        $params = ComponentHelper::getParams('com_ra_tools');
        $homeCode = strtoupper(trim((string) $params->get('default_group', '')));

        $nearest = $helper->getNearestOrganisations($homeCode, $neighbour_count, 'N');
        $groupList = implode(',', array_map(
                static fn($organisation) => $organisation->code,
                $nearest
        ));
 
        if (!$helper->updateComponentParam('com_ra_tools','group_list' , $groupList)) {
            throw new \RuntimeException('Unable to update RA Tools group list.');
        }       
    }

    protected function loadFormData()
    {
        return Factory::getApplication()->getUserState('com_ra_setup.three.data', []);
    }
}
