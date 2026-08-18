<?php

/**
 * @version    1.3.0
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 19/02/25 CB set up $this->user from getCurrentUser
 */

namespace Ramblers\Component\Ra_setup\Administrator\View\Reports;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\Tools;

//use Ramblers\Component\ra_tools\Administrator\Helper\ToolsHelper;
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $user;

    public function display($tpl = null) {
        $app = Factory::getApplication();
        $this->user = $this->getCurrentUser();
        parent::display($tpl);
    }

}