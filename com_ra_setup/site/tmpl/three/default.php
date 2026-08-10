<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Three: Walks programmes</h2>
    <div class="alert alert-info">
        The home organisation, neighbouring organisations and save operations will be added when this stub is implemented.
    </div>

    <?php 
    $rows = $this->setupHelper->getNearestOrganisations($this->default_group , 8,'Y');
    $sql =  'SELECT note, title, published FROM #__menu ';
    $sql .= 'WHERE note <> "" AND title <> "" ';
    $sql .= 'ORDER BY note, title, published ';
    $this->toolsHelper->showQuery($sql);
    echo $this->form->renderFieldset('setup'); 
    ?>

    <form action="index.php?option=com_ra_setup" method="post" class="d-inline">
        <button type="submit" name="task" value="three.previous" class="btn btn-secondary">Previous</button>
        <input type="hidden" name="option" value="com_ra_setup">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
    <a class="btn btn-primary" href="index.php?option=com_ra_setup&amp;view=four">Next</a>
</div>
<?php
