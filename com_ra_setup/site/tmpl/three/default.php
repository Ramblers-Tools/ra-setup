<?php

defined('_JEXEC') or die;

?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Three: Walks programmes</h2>
    <div class="alert alert-info">
        The home organisation, neighbouring organisations and save operations will be added when this stub is implemented.
    </div>

    <?php 
    $rows = $this->setupHelper->getNearestOrganisations($this->default_group , 5,'Y');
$sql =  'SELECT note, title, published FROM #__menu ';
$sql .= 'WHERE note <> "" AND title <> "" ';
$sql .= 'ORDER BY note, title, published ';
$this->toolsHelper->showQuery($sql);
    echo $this->form->renderFieldset('setup'); 
    ?>

    <a class="btn btn-secondary" href="index.php?option=com_ra_setup&amp;view=two">Previous</a>
    <a class="btn btn-primary" href="index.php?option=com_ra_setup&amp;view=four">Next</a>
</div>
<?php

