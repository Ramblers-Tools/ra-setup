<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Eight: Final confirmation</h2>

    <p>
        All the information necessary has been provided, and with the exception of the text for the front page, the site has been set up as requested.
    </p>
    <p>
        If you confirm your choices and continue, the front page will be updated and the wizard will only be available from the administrative back end.
    </p>
    <p>
        If you press cancel, you will be able to see for yourself how the site is currently configured, and can return to this wizard later.
    </p>

    <form action="index.php?option=com_ra_setup" method="post">
        <div class="mb-3">
            <button type="submit" name="task" value="eight.next" class="link-button button-p0186">
                Confirm your choices and continue
            </button>
        </div>
        <div>
            <button type="submit" name="task" value="eight.previous" class="link-button btn-secondary">Previous</button>
            <button type="submit" name="task" value="eight.cancel" class="link-button button-p0110">Cancel</button>
        </div>

        <input type="hidden" name="option" value="com_ra_setup">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
