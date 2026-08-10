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
        action="index.php?option=com_ra_setup"
        method="post"
        class="form-validate form-horizontal"
    >
        <?php echo $this->form->renderFieldset('setup'); ?>

        <button
            type="submit"
            name="task"
            value="five.previous"
            class="btn btn-secondary"
            formnovalidate
        >Previous</button>
        <button type="submit" name="task" value="five.update" class="validate btn btn-primary">Continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
