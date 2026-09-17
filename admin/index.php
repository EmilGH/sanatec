<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';

$courses = catalog_all('courses');
$routes  = catalog_all('routes');

$livePublished = static fn (array $rows): int => count(array_filter($rows, static fn (array $r): bool => (bool) $r['is_published']));

/**
 * Things that are worth doing something about, in the order they cost the shop
 * money. Nothing here is an error — it is the list of blanks left to fill.
 */
$todo = [];

if (!has_setting('addr_locality')) {
    $todo[] = ['Nobody can tell where you are. The page shows no location and search engines get no address.',
               'Site content → Location', '/admin/settings.php#location'];
}
if (!has_setting('opening_hours')) {
    $todo[] = ['No opening hours published.', 'Site content → Location', '/admin/settings.php#location'];
}
if (setting('included_publish') !== '1') {
    $todo[] = ['"What is included" is written but hidden. It is drafted from ordinary Riviera Maya practice — check every line against what you actually provide, then switch it on.',
               'Site content → What is included', '/admin/settings.php#included'];
}

$missingEs = (int) db()->query(
    "SELECT (SELECT COUNT(*) FROM courses WHERE TRIM(name_es) = '')
          + (SELECT COUNT(*) FROM routes  WHERE TRIM(name_es) = '')"
)->fetchColumn();

if ($missingEs > 0) {
    $todo[] = [$missingEs . ' catalogue ' . ($missingEs === 1 ? 'entry has' : 'entries have') . ' no Spanish name. The Spanish page falls back to English for those.',
               'Courses and Cenotes', '/admin/courses.php'];
}

$ogImage = setting('og_image');
if ($ogImage !== '' && !is_file(SANATEC_ROOT . '/' . ltrim($ogImage, '/'))) {
    $todo[] = ['The link-preview image is missing, so sharing the site on WhatsApp shows no picture.',
               'Site content → Search engines', '/admin/settings.php#meta'];
}

if (!empty($currentUser['must_change_password'])) {
    $todo[] = ['You are still using the password this account was created with.',
               'Change it now', '/admin/password.php'];
}

admin_header('Overview');
?>
<h1>Overview</h1>
<p class="lede">Signed in as <strong><?= e($currentUser['username']) ?></strong>.
  Changes appear on the public site as soon as you save — there is nothing to publish or deploy.</p>

<div class="row" style="margin-bottom:26px">
  <div class="card" style="margin:0">
    <div class="muted" style="font-size:13px">Courses</div>
    <div style="font-size:30px;font-weight:600"><?= $livePublished($courses) ?><span class="muted" style="font-size:16px;font-weight:400"> of <?= count($courses) ?> shown</span></div>
    <p style="margin:10px 0 0"><a href="/admin/courses.php">Manage courses →</a></p>
  </div>
  <div class="card" style="margin:0">
    <div class="muted" style="font-size:13px">Cenote routes</div>
    <div style="font-size:30px;font-weight:600"><?= $livePublished($routes) ?><span class="muted" style="font-size:16px;font-weight:400"> of <?= count($routes) ?> shown</span></div>
    <p style="margin:10px 0 0"><a href="/admin/routes.php">Manage cenotes →</a></p>
  </div>
  <div class="card" style="margin:0">
    <div class="muted" style="font-size:13px">Public site</div>
    <div style="font-size:30px;font-weight:600">EN · ES</div>
    <p style="margin:10px 0 0">
      <a href="/" target="_blank" rel="noopener">English ↗</a> ·
      <a href="/es/" target="_blank" rel="noopener">Español ↗</a>
    </p>
  </div>
</div>

<?php if ($todo !== []): ?>
  <h2>Worth doing</h2>
  <div class="card note">
    <?php foreach ($todo as $i => [$text, $where, $link]): ?>
      <div style="<?= $i > 0 ? 'margin-top:16px;padding-top:16px;border-top:1px solid #4a3a30' : '' ?>">
        <p style="margin:0 0 6px"><?= e($text) ?></p>
        <a class="btn tiny" href="<?= e($link) ?>"><?= e($where) ?> →</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="flash ok">Everything is filled in. Nothing needs attention.</div>
<?php endif; ?>

<h2>Recent changes</h2>
<div class="card" style="padding:0;overflow-x:auto">
  <table>
    <thead>
      <tr><th>When</th><th>Who</th><th>What</th></tr>
    </thead>
    <tbody>
    <?php $recent = audit_recent(12); ?>
    <?php if ($recent === []): ?>
      <tr><td colspan="3" class="muted">No changes recorded yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $entry): ?>
      <tr>
        <td class="muted num"><?= e(date('j M, H:i', strtotime((string) $entry['created_at']))) ?></td>
        <td class="muted"><?= e($entry['admin_user']) ?></td>
        <td>
          <?= e(ucfirst($entry['action'])) ?> <span class="muted"><?= e($entry['entity']) ?></span>
          <?php if ($entry['summary'] !== ''): ?><br><span class="muted"><?= e($entry['summary']) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
