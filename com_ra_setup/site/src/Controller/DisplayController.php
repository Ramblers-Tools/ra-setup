<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class DisplayController extends BaseController
{
    protected $default_view = 'one';

    public function __construct(
        $config = [],
        ?MVCFactoryInterface $factory = null,
        $app = null,
        $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);
    }

    public function display($cachable = false, $urlparams = false)
    {
        $view = $this->input->getCmd('view', $this->default_view);
        $this->input->set('view', $view);

        if (preg_match('/^[1-8]$/', (string) $view)) {
            (new SetupHelper)->logWizardStepStart((int) $view);
        } elseif (in_array($view, ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight'], true)) {
            (new SetupHelper)->logWizardStepStart(
                array_search($view, ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight'], true) + 1
            );
        }

        return parent::display($cachable, $urlparams);
    }
}
