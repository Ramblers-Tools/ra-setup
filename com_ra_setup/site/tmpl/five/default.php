<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Five: Committee members — names</h2>

    <form
        id="five-form"
        action="index.php?option=com_ra_setup&amp;task=five.update"
        method="post"
        class="form-validate form-horizontal"
    >
        <?php echo $this->form->renderFieldset('setup'); ?>

        <a class="btn btn-secondary" href="index.php?option=com_ra_setup&amp;view=four">Previous</a>
        <button type="submit" class="validate btn btn-primary">Continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <input type="hidden" name="task" value="five.update">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
