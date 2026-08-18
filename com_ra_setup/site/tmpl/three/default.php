<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');
echo '<div class="uk-card uk-card-default uk-card-body">';
echo '<h2>RA Setup Step Three: Walks programmes</h2>';
if ($this->admin == 'Y') {
    echo '<div class="alert alert-info">';
    echo 'This step will only work as expected if your site was derived from the template provided by Ramblers Tools.<br>'
    . ' If you are using a different template, you will need to create a menu item of type "Single programme" '
    . 'for each of your walks programmes, and define the "Options" to suit your requirements.';
    echo '</div>';
}
echo '<div class="uk-card uk-card-default uk-card-body">';
    ?>

    <form
        id="three-form"
        action="index.php?option=com_ra_setup"
        method="post"
        class="form-validate form-horizontal"
    >
        <?php echo $this->form->renderFieldset('setup'); ?>

        <button
            type="submit"
            name="task"
            value="three.previous"
            class="link-button btn-secondary"
            formnovalidate
        >Previous</button>
        <button type="submit" name="task" value="three.update" class="validate link-button btn-primary">Update and continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
<?php
    $this->toolsHelper->getNearestOrganisations($this->default_group , 6,'Y');


