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
        action="index.php?option=com_ra_setup&amp;task=two.update"
        method="post"
        class="form-validate form-horizontal"
    >
        <?php echo $this->form->renderField('title'); ?>
        <?php echo $this->form->renderField('body'); ?>
        <?php echo $this->form->renderField('facebook'); ?>
        <?php echo $this->form->renderField('facebook_link'); ?>

        <a class="btn btn-secondary" href="index.php?option=com_ra_setup&amp;view=one">Previous</a>
        <button type="submit" class="validate btn btn-primary">Update and continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <input type="hidden" name="task" value="two.update">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
