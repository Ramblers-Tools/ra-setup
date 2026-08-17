<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step One</h2>

    <form
        id="one-form"
        action="<?php echo Route::_('index.php?option=com_ra_setup&task=one.update'); ?>"
        method="post"
        class="form-validate form-horizontal"
        >
            <?php
            //       echo $this->form->renderField('area_group');
            echo $this->form->renderField('group_code');
            $target = 'https://staffordshireramblers.org/useful-contacts/organisation/groups.html';
            echo 'You can look up your Group code ' . $this->toolsHelper->buildLink($target,'here',true) . '<br>';
            //       echo $this->form->renderField('area_code');
            echo $this->form->renderField('site_name');
            echo $this->form->renderField('strapline');
            ?>

        <button type="submit" class="validate btn btn-primary">
            Update and continue
        </button>

        <input type="hidden" name="option" value="com_ra_setup">
        <input type="hidden" name="task" value="one.update">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
