<?php
defined('_JEXEC') or die;
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step One</h2>
    <p>This front-end view now uses the MVC structure expected for a Joomla component.</p>
    <p>The matching controller and model are available for the first view.</p>
</div>

<?php
//    return an array of the nearest organisations and their distances
$rows = $this->setupHelper->getNearestOrganisations('NS03');

foreach ($rows as $row) {
    echo $row->code;
    echo ' - ' . htmlspecialchars($row->name);
    echo ' - ' . number_format($row->distance, 2) .
    " Miles<br>";
}

$this->setupHelper->updateComponentParam('com_ra_tools', 'email_new_user', 'charlie@ramblers.tools');
/*
$this->setupHelper->updateComponentParams('com_ra_tools', [
    'home_group' => 'NS03',
    'away_group' => 'NS04',
    'season' => '2026',
    'debug' => 1,
]);
 *
 */
