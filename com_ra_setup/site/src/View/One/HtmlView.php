<?php

namespace Ramblers\Component\Ra_setup\Site\View\One;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $this->document->setTitle('RA Setup Step One');

        return parent::display($tpl);
    }
}
