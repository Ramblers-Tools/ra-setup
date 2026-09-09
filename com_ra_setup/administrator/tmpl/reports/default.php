<?php
/**
 * 14/08/26 CB Created
 *  09/09/26 CB completeion report added
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
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$toolsHelper = new ToolsHelper;
ToolBarHelper::title('Reports for Setup');

// Import CSS
$this->wa = $this->document->getWebAssetManager();
$this->wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
$breadcrumbs = $toolsHelper->buildLink('administrator/index.php', 'Home Dashboard');
$breadcrumbs .= '>' . $toolsHelper->buildLink($back, 'RA Dashboard');
echo $breadcrumbs;

$reports = [
    'Completion report' => 'administrator/index.php?option=com_ra_setup&task=reports.completion&type=group',
    'Log report' => 'administrator/index.php?option=com_ra_setup&task=reports.showLog',
    'Walks Programme - Single Group' => 'administrator/index.php?option=com_ra_setup&task=reports.showEntries&type=group',
    'Walks Programme - Multiple Groups' => 'administrator/index.php?option=com_ra_setup&task=reports.showEntries&type=groups',
    'Walks Programme - By radius' => 'administrator/index.php?option=com_ra_setup&task=reports.showEntries&type=radius',
    'Walks Programme - Other' => 'administrator/index.php?option=com_ra_setup&task=reports.showOther',
];
?>

<form action="<?php echo Route::_('index.php?option=com_ra_setup&view=reports'); ?>" method="post" name="reportsForm" id="reportsForm">
    <div id="j-main-container" class="span10">
        <div class="clearfix"> </div>
        <?php
        echo '<ul>';
        foreach ($reports as $caption => $task) {
            echo '<li>' . $toolsHelper->buildLink($task, $caption) . '</li>';
        }
        echo '</ul>';

        echo $toolsHelper->backButton($back);
        ?>
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</div>
</form>
<?php
