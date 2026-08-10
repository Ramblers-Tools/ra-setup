<?php

namespace Ramblers\Component\Ra_setup\Site\View\Six;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $people;

    public function display($tpl = null)
    {
        (new SetupHelper)->assertCanRunWizard();
        $this->form = $this->get('Form');
        $this->people = $this->get('People');

        if (!$this->form) {
            throw new \RuntimeException('Unable to load the Step 6 form.', 500);
        }

        if (empty($this->people)) {
            throw new \RuntimeException('Complete Step 5 before opening Step 6.', 400);
        }

        $this->document->setTitle('RA Setup Step Six');

        return parent::display($tpl);
    }
}
