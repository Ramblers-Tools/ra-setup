<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\FormModel;

class FourModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_ra_setup.four',
            'four',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }
}
