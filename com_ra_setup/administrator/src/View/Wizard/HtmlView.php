<?php

namespace Ramblers\Component\Ra_setup\Administrator\View\Wizard;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $this->document->setTitle('RA Setup Wizard');

        return parent::display($tpl);
    }
}
