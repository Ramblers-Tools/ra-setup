<?php

namespace Ramblers\Component\Ra_setup\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

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

        return parent::display($cachable, $urlparams);
    }
}
