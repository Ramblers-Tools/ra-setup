<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Four: Optional components</h2>

    <form
        id="four-form"
        action="index.php?option=com_ra_setup&amp;task=four.update"
        method="post"
        class="form-validate form-horizontal"
    >
        <?php echo $this->form->renderFieldset('setup'); ?>

        <p class="form-text">
            Selecting RA Events also enables its module and plugins. Selecting RA Mailman also enables RA Delivery, RA Members and the required plugins.
        </p>

        <a class="btn btn-secondary" href="index.php?option=com_ra_setup&amp;view=three">Previous</a>
        <button type="submit" class="validate btn btn-primary">Update and continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <input type="hidden" name="task" value="four.update">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
