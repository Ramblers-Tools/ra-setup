<?php

namespace Ramblers\Component\Ra_setup\Site\View\Three;

defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\User\CurrentUserInterface;
use \Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $app;
    protected $default_group;
    protected $form;
    protected $setupHelper;
    protected $toolsHelper;
    

    public function display($tpl = null)
    {
        $this->app = Factory::getApplication();
        $app = Factory::getApplication();
        $this->user = $this->app->getSession()->get('user');
        //       var_dump($this->user);
        //      die('id=' . $this->user->id);
        $this->setupHelper = new SetupHelper;
        $this->toolsHelper = new ToolsHelper;
        $this->setupHelper = new SetupHelper;
        $this->setupHelper->assertCanRunWizard();
        $this->form = $this->get('Form');
		$params = ComponentHelper::getParams('com_ra_tools');
		$this->default_group = $params->get('default_group'); 
        if (!$this->form) {
            throw new \RuntimeException('Unable to load the Step 3 form.', 500);
        }

        $this->document->setTitle('RA Setup Step Three');

        return parent::display($tpl);
    }
}
