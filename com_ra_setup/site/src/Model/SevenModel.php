<?php

namespace Ramblers\Component\Ra_setup\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\FormModel;

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
}
