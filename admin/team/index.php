<?php

declare(strict_types=1);

require __DIR__ . '/../_init.php';
require __DIR__ . '/../_layout.php';
require_once __DIR__ . '/../../src/Team.php';

$currentUser = require_permission('can_manage_team');
$team = team_list();

shell_start('Team', $currentUser);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h1 class="h3 mb-0">Team</h1>
  <a class="btn btn-aqua" href="/admin/team/edit.php?new=1"><i class="fa-solid fa-user-plus me-1"></i>Add team member</a>
</div>

<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Name</th><th class="d-none d-md-table-cell">Roles</th><th class="d-none d-lg-table-cell">Contact</th><th>Access</th><th></th></tr></thead>
  <tbody>
  <?php if ($team === []): ?><tr><td colspan="5" class="text-secondary">Nobody yet.</td></tr><?php endif; ?>
  <?php foreach ($team as $m): ?>
    <tr class="<?= $m['is_active'] ? '' : 'opacity-50' ?>">
      <td>
        <a class="fw-semibold text-decoration-none" href="/admin/team/edit.php?id=<?= (int) $m['id'] ?>"><?= e($m['name']) ?></a>
        <?php if ($m['job_title']): ?><div class="small text-secondary"><?= e($m['job_title']) ?></div><?php endif; ?>
        <?php if (!$m['is_active']): ?><span class="badge text-bg-secondary">inactive</span><?php endif; ?>
      </td>
      <td class="d-none d-md-table-cell">
        <?php foreach (TEAM_ROLES as $flag => $label): if ($m[$flag]): ?><span class="badge text-bg-dark border me-1"><?= e($label) ?></span><?php endif; endforeach; ?>
      </td>
      <td class="d-none d-lg-table-cell small text-secondary">
        <?= $m['email'] ? e($m['email']) . '<br>' : '' ?><?= $m['mobile'] ? e($m['mobile']) : '' ?>
      </td>
      <td class="small">
        <?php if ($m['is_system_admin']): ?><span class="badge text-bg-info">System admin</span>
        <?php else: $n = 0; foreach (TEAM_PERMISSIONS as $flag => $l) { $n += (int) $m[$flag]; } echo $n, ' of ', count(TEAM_PERMISSIONS), ' sections'; endif; ?>
        <?php if ($m['next_expiry'] !== null && strtotime($m['next_expiry']) < strtotime('+60 days')): ?>
          <div class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>credential <?= strtotime($m['next_expiry']) < time() ? 'expired' : 'expiring' ?></div>
        <?php endif; ?>
      </td>
      <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/admin/team/edit.php?id=<?= (int) $m['id'] ?>">Edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php shell_end($currentUser);
