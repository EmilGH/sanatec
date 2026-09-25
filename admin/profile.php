<?php

declare(strict_types=1);

/** A team member's own record: identity, how to reach them, public profile. */

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Team.php';
require __DIR__ . '/team/_parts.php';

$row = team_find((int) $currentUser['team']['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'save') {
        try {
            team_save_self($currentUser, $_POST);
            audit('update', 'team_member', $row['id'], 'own profile');
            flash('Saved.');
        } catch (Throwable $e) {
            flash($e->getMessage(), 'warn');
        }
        redirect('/admin/profile.php');
    }

    if (team_handle_subforms($action, (int) $currentUser['id'], (int) $row['id'], $currentUser)) {
        redirect('/admin/profile.php');
    }
}

shell_start('My profile', $currentUser);
?>
<h1 class="h3 mb-3">My profile</h1>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <?php team_identity_card($row, true); ?>
  <?php team_profile_card($row); ?>
  <button class="btn btn-aqua mb-4" type="submit">Save</button>
</form>
<?php team_photo_card($row, '/admin/profile.php'); ?>
<?php team_channels_card((int) $currentUser['id'], '/admin/profile.php'); ?>
<?php shell_end($currentUser);
