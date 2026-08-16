<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Four: Optional components</h2>

    <form
        id="four-form"
        action="index.php?option=com_ra_setup"
        method="post"
        class="form-validate form-horizontal"
    >
        <?php 
        echo $this->form->renderFieldset('setup'); 
        ?>

        <p class="form-text">
            Selecting RA Events also enables its module and plugins. Selecting RA Mailman also enables RA Members and the required plugins.
        </p>

        <button
            type="submit"
            name="task"
            value="four.previous"
            class="btn btn-secondary"
            formnovalidate
        >Previous</button>
        <button type="submit" name="task" value="four.update" class="validate btn btn-primary">Update and continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
