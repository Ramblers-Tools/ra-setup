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

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\User\CurrentUserInterface;
use \Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;

class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $form;
    protected $setupHelper;

    public function display($tpl = null) {
        $this->setupHelper = new SetupHelper;
        $this->setupHelper->assertCanRunWizard();
        $this->form = $this->get('Form');

        if (!$this->form) {
            throw new \RuntimeException('Unable to load the Step 2 form.', 500);
        }

        $this->document->setTitle('RA Setup Step Two');
        return parent::display($tpl);
    }

}
