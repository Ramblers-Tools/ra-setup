<?php

namespace Ramblers\Component\Ra_setup\Site\View\Eight;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $helper = new SetupHelper;
        $helper->assertCanRunWizard();
        $helper->assertWizardNotCompleted();
        $this->document->setTitle('RA Setup Final Confirmation');

        return parent::display($tpl);
    }
}
