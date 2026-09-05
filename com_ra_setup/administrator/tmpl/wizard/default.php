<?php
/**
 * 05/08/26 CB created
 * 15/08/26 CB open links in new window
 * 17/08/26 CB pass parameter to step 3
 * 01/09/26 CB remove option to configure emails
 */
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_setup\Site\Helper\SetupHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

ToolBarHelper::title('Installation Wizard');

// Import CSS
$this->wa = $this->document->getWebAssetManager();
$this->wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$this->wa->registerAndUseStyle('dashboard', 'com_ra_tools/dashboard.css');

$back = 'administrator/index.php?option=com_ra_tools&view=dashboard';

?>

<?php
$reports = [
    'System setup' => 'index.php?option=com_ra_setup&view=one',
    'Home page' => 'index.php?option=com_ra_setup&view=two',
    'Walks programmes ' => 'index.php?option=com_ra_setup&view=three&admin=Y',
    'Optional components' => 'index.php?option=com_ra_setup&view=four',
    'Committee members' => 'index.php?option=com_ra_setup&view=five',
];

if (!(new SetupHelper)->isWizardCompleted()) {
    $reports['Final confirmation'] = 'index.php?option=com_ra_setup&view=eight';
}
?>
<form action="<?php echo JRoute::_('index.php?option=com_ra_setup&view=wizard'); ?>" method="post" name="reportsForm" id="reportsForm">
    <div id="j-main-container" class="span10">
        <div class="clearfix"> </div>
        <?php
        echo '<div class="dashboard-grid">';
        echo $this->toolsHelper->buildDashboardReportBlock('Steps', $reports,true);
        echo '</div>';
        echo $this->toolsHelper->backButton($back);
        ?>
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</div>
</form>
