<?php
/*
* 07/09/26 CB restrict group list to specified number of nearby groups
09/09/26 CB set up corrently configured menu items for walks selection
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
        $app = Factory::getApplication();
        $saved = $app->getUserState('com_ra_setup.three.data', []);
        // Get the form.
        $form = $this->loadForm('com_ra_setup.three', 'three', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        // Values retained after returning from the next/previous step (or
        // after validation failure) must take precedence over menu defaults.
        if (!empty($saved)) {
            return $form;
        }

        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT published FROM #__menu ';
        $sql .= 'WHERE alias=';

        $single = $toolsHelper->getValue($sql .'"walks-s"');
        if ($single == '1'){
            $form->setValue('walks_scope', null, 'home');
        } else {
            $form->setValue('walks_scope', null, 'other');
            $radius = $toolsHelper->getValue($sql .'"walks-r"');
            if ($radius == '1'){
                $form->setValue('selection_method', null, 'distance');
            } else {
                $form->setValue('selection_method', null, 'neighbours');
            }
        }

        return $form;
    }

    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ra_setup.three.data', []);
        return $data;
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

        $nearestCount = $mode === 'home' ? 0 : ($neighbour_count > 0 ? $neighbour_count : 5);
        $nearest = $helper->getNearestOrganisations($homeCode, $nearestCount, 'N');
        $groupCodes = array_merge(
                [$homeCode],
                array_map(static fn($organisation) => $organisation->code, $nearest)
        );
        $groupList = implode(',', array_values(array_unique($groupCodes)));
 
        if (!$helper->updateComponentParam('com_ra_tools','group_list' , $groupList)) {
            throw new \RuntimeException('Unable to update RA Tools group list.');
        }       
    }
}
