<?php

namespace Ramblers\Component\Ra_setup\Site\View\Seven;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;

    public function display($tpl = null)
    {
        (new SetupHelper)->assertCanRunWizard();

        if (!ComponentHelper::isEnabled('com_ra_mailman')) {
            throw new \RuntimeException('Step 7 is only available when RA Mailman is enabled.', 404);
        }

        $this->form = $this->get('Form');

        if (!$this->form) {
            throw new \RuntimeException('Unable to load the Step 7 form.', 500);
        }

        $this->document->setTitle('RA Setup Step Seven');

        return parent::display($tpl);
    }
}
