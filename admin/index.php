<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';

$courses = catalog_all('courses');
$routes  = catalog_all('routes');
$shown = static fn (array $rows): int => count(array_filter($rows, static fn (array $r): bool => (bool) $r['is_published']));

/** Blanks worth filling, in the order they cost the shop money. */
$todo = [];
if (!has_setting('addr_locality')) {
    $todo[] = ['Nobody can tell where you are — no location on the page, no address for search engines.', 'Business → Location', '/admin/settings.php#location'];
}
if (!has_setting('opening_hours')) {
    $todo[] = ['No opening hours published.', 'Business → Location', '/admin/settings.php#location'];
}
if (setting('included_publish') !== '1') {
    $todo[] = ['"What is included" is drafted but hidden. Check every line against what you actually provide, then switch it on.', 'Business → What is included', '/admin/settings.php#included'];
}
$missingEs = (int) db()->query("SELECT (SELECT COUNT(*) FROM courses WHERE TRIM(name_es)='') + (SELECT COUNT(*) FROM routes WHERE TRIM(name_es)='')")->fetchColumn();
if ($missingEs > 0) {
    $todo[] = [$missingEs . ' catalogue ' . ($missingEs === 1 ? 'entry has' : 'entries have') . ' no Spanish name.', 'Catalog', '/admin/courses.php'];
}

// Credentials lapsing within 60 days, or already lapsed.
$expiring = db()->query(
    'SELECT p.name, c.kind, c.title, c.expires_on, DATEDIFF(c.expires_on, CURDATE()) AS days
     FROM team_credentials c
     JOIN team_members t ON t.id = c.team_member_id
     JOIN people p ON p.id = t.person_id
     WHERE c.expires_on IS NOT NULL AND c.expires_on <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND t.is_active = 1
     ORDER BY c.expires_on'
)->fetchAll();

$recent = audit_recent(10);

shell_start('Overview', $currentUser);
?>
<div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
  <h1 class="h3 mb-0">Overview</h1>
  <span class="text-secondary small">Signed in as <?= e($currentUser['name']) ?><?= $currentUser['team']['job_title'] ? ' · ' . e($currentUser['team']['job_title']) : '' ?></span>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
    <div class="text-secondary small">Courses</div>
    <div class="stat"><?= $shown($courses) ?><span class="text-secondary fs-6 fw-normal"> / <?= count($courses) ?></span></div>
    <a class="small" href="/admin/courses.php">Manage →</a>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
    <div class="text-secondary small">Cenote routes</div>
    <div class="stat"><?= $shown($routes) ?><span class="text-secondary fs-6 fw-normal"> / <?= count($routes) ?></span></div>
    <a class="small" href="/admin/routes.php">Manage →</a>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
    <div class="text-secondary small">Team</div>
    <div class="stat"><?= (int) db()->query('SELECT COUNT(*) FROM team_members WHERE is_active = 1')->fetchColumn() ?></div>
    <a class="small" href="/admin/team/">Manage →</a>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
    <div class="text-secondary small">Public site</div>
    <div class="stat">EN · ES</div>
    <a class="small" href="/" target="_blank" rel="noopener">English ↗</a> · <a class="small" href="/es/" target="_blank" rel="noopener">Español ↗</a>
  </div></div></div>
</div>

<?php if ($expiring !== []): ?>
<h2 class="h5 mt-4">Credentials to renew</h2>
<div class="card mb-4"><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th>Who</th><th>What</th><th>Expires</th></tr></thead><tbody>
  <?php foreach ($expiring as $c): ?>
    <tr class="<?= (int) $c['days'] < 0 ? 'table-danger' : '' ?>">
      <td><?= e($c['name']) ?></td>
      <td><?= e($c['title'] ?: $c['kind']) ?></td>
      <td><?= e($c['expires_on']) ?> <span class="text-secondary small">(<?= (int) $c['days'] < 0 ? abs((int) $c['days']) . ' days ago' : 'in ' . (int) $c['days'] . ' days' ?>)</span></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; ?>

<?php if ($todo !== []): ?>
<h2 class="h5 mt-4">Worth doing</h2>
<div class="card mb-4"><ul class="list-group list-group-flush">
  <?php foreach ($todo as [$text, $where, $link]): ?>
    <li class="list-group-item bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span><?= e($text) ?></span>
      <a class="btn btn-sm btn-outline-info" href="<?= e($link) ?>"><?= e($where) ?> →</a>
    </li>
  <?php endforeach; ?>
</ul></div>
<?php endif; ?>

<h2 class="h5 mt-4">Recent changes</h2>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead><tbody>
  <?php if ($recent === []): ?><tr><td colspan="3" class="text-secondary">Nothing yet.</td></tr><?php endif; ?>
  <?php foreach ($recent as $r): ?>
    <tr>
      <td class="text-secondary text-nowrap"><?= e(date('j M, H:i', strtotime((string) $r['created_at']))) ?></td>
      <td class="text-secondary"><?= e($r['admin_user']) ?></td>
      <td><?= e(ucfirst($r['action'])) ?> <span class="text-secondary"><?= e($r['entity']) ?></span>
        <?php if ($r['summary'] !== ''): ?><br><span class="text-secondary small"><?= e($r['summary']) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php shell_end();
