<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');
?>
<div class="uk-card uk-card-default uk-card-body">
    <h2>RA Setup Step Six: Committee members — roles</h2>

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
