<?php

defined('_JEXEC') or die;
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Seven: Email configuration</h2>
    <div class="alert alert-info">
        Domain defaults, SMTP2GO provisioning, RA Delivery parameter updates and final completion processing remain to be implemented.
    </div>

    <?php echo $this->form->renderFieldset('setup'); ?>

    <a class="btn btn-secondary" href="index.php?option=com_ra_setup&amp;view=six">Previous</a>
    <button class="btn btn-primary" type="button" disabled>Complete setup</button>
</div>
