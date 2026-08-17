<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Two: Home page</h2>

    <form
        id="two-form"
        action="index.php?option=com_ra_setup"
        method="post"
        class="form-validate form-horizontal"
    >
        <?php echo $this->form->renderField('title'); ?>
        <?php echo $this->form->renderField('body'); ?>
        <?php echo $this->form->renderField('facebook'); ?>
        <?php echo $this->form->renderField('facebook_link'); ?>
         <?php echo $this->form->renderField('facebook_text'); ?>

        <button
            type="submit"
            name="task"
            value="two.previous"
            class="link-button btn-secondary"
            formnovalidate
        >Previous</button>
        <button type="submit" name="task" value="two.update" class="validate link-button btn-primary">Update and continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
