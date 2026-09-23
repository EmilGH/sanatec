<?php

declare(strict_types=1);

require __DIR__ . '/../_init.php';
require __DIR__ . '/../_layout.php';
require_once __DIR__ . '/../../src/Team.php';
require __DIR__ . '/_parts.php';

$currentUser = require_permission('can_manage_team');
$actorIsAdmin = (int) $currentUser['team']['is_system_admin'] === 1;

$teamId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$row = $teamId ? team_find($teamId) : null;
if ($teamId && $row === null) {
    flash('That team member no longer exists.', 'warn');
    redirect('/admin/team/');
}
$isSelf = $row !== null && (int) $row['person_id'] === (int) $currentUser['id'];
$self = '/admin/team/edit.php' . ($teamId ? '?id=' . $teamId : '?new=1');
$signInLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'save') {
        try {
            $savedId = team_save($teamId, $_POST, $currentUser);
            audit($teamId ? 'update' : 'create', 'team_member', $savedId, post('name'));
            flash($teamId ? 'Saved.' : 'Team member added. Now add a way to reach them.');
            redirect('/admin/team/edit.php?id=' . $savedId);
        } catch (Throwable $e) {
            flash($e->getMessage(), 'warn');
            redirect($self);
        }
    }

    if ($action === 'login_link' && $row !== null) {
        $channels = person_channels((int) $row['person_id']);
        $channel = $channels[0] ?? null;
        if ($channel === null) {
            flash('Add a contact channel first — a link has to be issued against one.', 'warn');
            redirect($self);
        }
        $signInLink = login_issue_link((int) $row['person_id'], (int) $channel['id'], 30);
        audit('login_link', 'team_member', $teamId, 'issued for ' . $row['name']);
    } elseif ($row !== null && team_handle_subforms($action, (int) $row['person_id'], $teamId, $currentUser)) {
        redirect($self);
    }
}

shell_start($row ? $row['name'] : 'New team member', $currentUser);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <a class="small text-secondary text-decoration-none" href="/admin/team/"><i class="fa-solid fa-arrow-left me-1"></i>Team</a>
    <h1 class="h3 mb-0"><?= $row ? e($row['name']) : 'New team member' ?></h1>
  </div>
  <?php if ($row): ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="login_link">
    <button class="btn btn-outline-info" type="submit" title="A one-time link that signs this person in — hand it to them yourself"><i class="fa-solid fa-link me-1"></i>Get sign-in link</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($signInLink !== null): ?>
  <div class="alert alert-info">
    <div class="fw-semibold mb-1">One-time sign-in link for <?= e($row['name']) ?> — works once, expires in 30 minutes.</div>
    <input class="form-control font-monospace small" readonly value="<?= e($signInLink) ?>" onclick="this.select()">
    <div class="small mt-1">Paste it to them yourself. It is shown only this once.</div>
  </div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <?php team_identity_card($row ?? [], false); ?>
  <?php team_roles_card($row ?? ['is_active' => 1], $actorIsAdmin, $isSelf); ?>
  <?php team_profile_card($row ?? []); ?>
  <div class="d-flex gap-2 mb-4">
    <button class="btn btn-aqua" type="submit"><?= $row ? 'Save' : 'Create' ?></button>
    <a class="btn btn-outline-secondary" href="/admin/team/">Back</a>
  </div>
</form>

<?php if ($row): ?>
  <?php team_channels_card((int) $row['person_id'], $self); ?>
  <?php team_credentials_card($teamId); ?>
<?php else: ?>
  <p class="text-secondary small">Contact channels and credentials can be added once the person is created.</p>
<?php endif; ?>
<?php shell_end();
