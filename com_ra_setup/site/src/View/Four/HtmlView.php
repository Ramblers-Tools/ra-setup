<?php

namespace Ramblers\Component\Ra_setup\Site\View\Four;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $toolsHelper;

    public function display($tpl = null)
    {
        (new SetupHelper)->assertCanRunWizard();
        $this->toolsHelper = new ToolsHelper;
        $this->form = $this->get('Form');

        if (!$this->form) {
            throw new \RuntimeException('Unable to load the Step 4 form.', 500);
        }

        $this->document->setTitle('RA Setup Step Four');

        return parent::display($tpl);
    }
}
