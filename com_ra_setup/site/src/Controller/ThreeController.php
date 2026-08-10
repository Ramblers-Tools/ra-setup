<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class ThreeController extends BaseController
{
    public function display($cachable = false, $urlparams = false)
    {
        return parent::display($cachable, $urlparams);
    }

    public function previous()
    {
        $this->checkToken();
        (new SetupHelper)->assertCanRunWizard();
        $this->setRedirect('index.php?option=com_ra_setup&view=two');

        return true;
    }
}
