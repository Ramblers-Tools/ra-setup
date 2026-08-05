<?php

namespace Ramblers\Component\Ra_setup\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'wizard';

    public function display($cachable = false, $urlparams = false)
    {
        $view = $this->input->getCmd('view', $this->default_view);
        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }
}
