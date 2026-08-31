<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$wa->useScript('keepalive')->useScript('form.validate');
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Six: Committee members — contact details and permissions</h2>

    <form
        id="six-form"
        action="index.php?option=com_ra_setup"
        method="post"
    >
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email address</th>
                        <th scope="col">Role(s)</th>
                        <?php if ($this->mailmanEnabled): ?>
                            <th scope="col" class="text-center">Mailing lists</th>
                        <?php endif; ?>
                        <?php if ($this->eventsEnabled): ?>
                            <th scope="col" class="text-center">Events</th>
                        <?php endif; ?>
                        <?php if ($this->membersEnabled): ?>
                            <th scope="col" class="text-center">Access membership</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->people as $key => $person): ?>
                        <tr>
                            <td>
                                <label class="visually-hidden" for="person-name-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                                    Full name for <?php echo htmlspecialchars($person['short_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </label>
                                <input
                                    id="person-name-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="form-control required"
                                    type="text"
                                    name="jform[people][<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][name]"
                                    value="<?php echo htmlspecialchars($person['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </td>
                            <td>
                                <label class="visually-hidden" for="person-email-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                                    Email address for <?php echo htmlspecialchars($person['short_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </label>
                                <input
                                    id="person-email-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="form-control required validate-email"
                                    type="email"
                                    name="jform[people][<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][email]"
                                    value="<?php echo htmlspecialchars($person['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </td>
                            <td><?php echo htmlspecialchars(implode('+', $person['roles']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php if ($this->mailmanEnabled): ?>
                                <td class="text-center">
                                    <input
                                        id="person-mailman-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="form-check-input"
                                        type="checkbox"
                                        name="jform[people][<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][mailman]"
                                        value="1"
                                        aria-label="Permit <?php echo htmlspecialchars($person['full_name'], ENT_QUOTES, 'UTF-8'); ?> to create mailing lists"
                                        <?php echo !empty($person['permissions']['mailman']) ? 'checked' : ''; ?>
                                    >
                                </td>
                            <?php endif; ?>
                            <?php if ($this->eventsEnabled): ?>
                                <td class="text-center">
                                    <input
                                        id="person-events-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="form-check-input"
                                        type="checkbox"
                                        name="jform[people][<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][events]"
                                        value="1"
                                        aria-label="Permit <?php echo htmlspecialchars($person['full_name'], ENT_QUOTES, 'UTF-8'); ?> to create events"
                                        <?php echo !empty($person['permissions']['events']) ? 'checked' : ''; ?>
                                    >
                                </td>
                            <?php endif; ?>
                            <?php if ($this->membersEnabled): ?>
                                <td class="text-center">
                                    <input
                                        id="person-members-<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="form-check-input"
                                        type="checkbox"
                                        name="jform[people][<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][members]"
                                        value="1"
                                        aria-label="Grant <?php echo htmlspecialchars($person['full_name'], ENT_QUOTES, 'UTF-8'); ?> access to membership data"
                                        <?php echo !empty($person['permissions']['members']) ? 'checked' : ''; ?>
                                    >
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button
            type="submit"
            name="task"
            value="six.previous"
            class="btn btn-secondary"
            formnovalidate
        >Previous</button>
        <button type="submit" name="task" value="six.update" class="btn btn-primary">Save and continue</button>

        <input type="hidden" name="option" value="com_ra_setup">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
