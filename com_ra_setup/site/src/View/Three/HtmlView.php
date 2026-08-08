<?php

namespace Ramblers\Component\Ra_setup\Site\View\Three;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;

    public function display($tpl = null)
    {
        (new SetupHelper)->assertCanRunWizard();
        $this->form = $this->get('Form');

        if (!$this->form) {
            throw new \RuntimeException('Unable to load the Step 3 form.', 500);
        }

        $this->document->setTitle('RA Setup Step Three');

        return parent::display($tpl);
    }
}
