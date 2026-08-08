<?php

/**
 * @version    4.7.8
 * @package    com_ra_setup
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * 07/09/26 CB created
 */

namespace Ramblers\Component\Ra_setup\Site\View\Two;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\User\CurrentUserInterface;
use \Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $app;
    protected $setupHelper;
    protected $toolsHelper;

    public function display($tpl = null) {
        $this->app = Factory::getApplication();
        $app = Factory::getApplication();
        $this->user = $this->app->getSession()->get('user');
        //       var_dump($this->user);
        //      die('id=' . $this->user->id);
        $this->setupHelper = new SetupHelper;
        $this->toolsHelper = new ToolsHelper;
        $this->document->setTitle('RA Setup Step Two');
        //       $date_completed = '2026-18-08';
        $date_completed = $this->setupHelper->wizardCompleted();

        if (is_null($date_completed)) {
            if (is_null($this->user)) {
                throw new \Exception($message, 404);
            }
        } else {
            //          if (!$this->toolsHelper->isSuperuser()) {
            //               $message = 'Configuration was completed ' . $date_completed . ' and can only be updated by a SuperUser';
            //               $this->app->enqueueMessage($message, 'warning');
            //               throw new \Exception($message, 404);
            //           }
        }
        return parent::display($tpl);
    }

}
