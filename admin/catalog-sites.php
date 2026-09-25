<?php

declare(strict_types=1);

/** Dive sites: the cenotes a route visits, and the stamps in a diver's passport. */

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Customers.php';
require_once __DIR__ . '/../src/Passport.php';

$currentUser = require_permission('can_manage_catalog');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        switch (post('action')) {
            case 'save':
                $savedId = dive_site_save($id ?: null, $_POST);
                audit($id ? 'update' : 'create', 'dive_site', $savedId, post('name_en'));
                flash($id ? 'Site updated.' : 'Site added.');
                break;
            case 'toggle':
                db()->prepare('UPDATE dive_sites SET is_published = 1 - is_published WHERE id = :id')->execute([':id' => $id]);
                break;
            case 'move':
                $rows = dive_sites(false);
                $ids = array_map('intval', array_column($rows, 'id'));
                $i = array_search($id, $ids, true);
                $j = $i + ((int) ($_POST['direction'] ?? 0) < 0 ? -1 : 1);
                if ($i !== false && isset($ids[$j])) {
                    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                    $upd = db()->prepare('UPDATE dive_sites SET sort_order = :o WHERE id = :id');
                    foreach ($ids as $o => $sid) {
                        $upd->execute([':o' => $o, ':id' => $sid]);
                    }
                }
                break;
            case 'delete':
                $inUse = (int) db()->query('SELECT COUNT(*) FROM dives WHERE dive_site_id = ' . $id)->fetchColumn();
                if ($inUse > 0) {
                    throw new RuntimeException('That site is in divers\' logbooks. Hide it instead of deleting it.');
                }
                $row = dive_site_find($id);
                db()->prepare('DELETE FROM dive_sites WHERE id = :id')->execute([':id' => $id]);
                audit('delete', 'dive_site', $id, (string) ($row['name_en'] ?? ''));
                flash('Site deleted.');
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
        redirect('/admin/catalog-sites.php' . ($id ? '?edit=' . $id : '?new=1'));
    }
    redirect('/admin/catalog-sites.php');
}

$editing = null;
if (isset($_GET['new'])) {
    $editing = ['is_published' => 1];
} elseif (isset($_GET['edit'])) {
    $editing = dive_site_find((int) $_GET['edit']);
    if ($editing === null) {
        flash('That site no longer exists.', 'warn');
        redirect('/admin/catalog-sites.php');
    }
}
$rows = dive_sites(false);
$usage = db()->query('SELECT dive_site_id, COUNT(*) AS n FROM dives GROUP BY dive_site_id')->fetchAll(PDO::FETCH_KEY_PAIR);

shell_start('Dive sites', $currentUser);
echo shell_page('Dive sites', 'Catalog', '<a class="btn btn-primary" href="/admin/catalog-sites.php?new=1">' . ui_icon('plus') . 'Add</a>');
?>
<p class="st-lede mb-3">Each cenote is a site. A route on the excursion list visits one or more of them in order, and a diver marked attended gets a stamp for each one.</p>
<div class="st-tabs mb-3">
  <a href="/admin/catalog-courses.php">Courses</a>
  <a href="/admin/catalog-excursions.php">Cenote excursions</a>
  <a href="/admin/catalog-sites.php" aria-current="page">Dive sites</a>
</div>

<?php if ($editing !== null): ?>
  <form method="post" class="st-card mb-4">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 class="st-card__title mb-3"><?= isset($editing['id']) ? 'Edit' : 'New' ?> site</h2>
    <?php field_pair('name', 'Cenote name', $editing); ?>
    <?php field_pair('description', 'A line for the passport', $editing, 'textarea', 'What makes this cenote this cenote. Optional.'); ?>
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><label class="form-label" for="max_depth_m">Max depth (m)</label><input class="form-control" id="max_depth_m" name="max_depth_m" inputmode="numeric" value="<?= e((string) ($editing['max_depth_m'] ?? '')) ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="cert_required">Cert. required</label>
        <select class="form-select" id="cert_required" name="cert_required"><option value="">—</option><?php foreach (CERT_LEVELS as $k => $l): ?><option value="<?= e($k) ?>" <?= ($editing['cert_required'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-3"><label class="form-label" for="latitude">Latitude</label><input class="form-control" id="latitude" name="latitude" inputmode="decimal" value="<?= e((string) ($editing['latitude'] ?? '')) ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="longitude">Longitude</label><input class="form-control" id="longitude" name="longitude" inputmode="decimal" value="<?= e((string) ($editing['longitude'] ?? '')) ?>"></div>
    </div>
    <input type="hidden" name="sort_order" value="<?= (int) ($editing['sort_order'] ?? count($rows)) ?>">
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>><label class="form-check-label" for="is_published">Divers can see this site (passport wish list)</label></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline-secondary" href="/admin/catalog-sites.php">Cancel</a></div>
  </form>
<?php endif; ?>

<div class="st-card" style="padding:0 16px">
  <div class="st-tablewrap"><table class="st-table">
    <thead><tr><th style="width:84px">Order</th><th>Cenote</th><th>Depth</th><th>Cert.</th><th>Logged dives</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $row): ?>
      <tr>
        <td data-label="Order" class="order-cell">
          <form method="post" style="display:inline-flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary" name="direction" value="-1" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
            <button class="btn btn-sm btn-outline-secondary" name="direction" value="1" title="Move down" <?= $i === count($rows) - 1 ? 'disabled' : '' ?>>↓</button></form></td>
        <td data-label="Cenote" class="st-table__main"><strong><?= e($row['name_en']) ?></strong><?= $row['name_es'] !== $row['name_en'] ? ' <span class="st-muted">/ ' . e($row['name_es']) . '</span>' : '' ?><br><small class="st-muted">/<?= e($row['slug']) ?></small></td>
        <td data-label="Depth" class="st-num"><?= $row['max_depth_m'] !== null ? (int) $row['max_depth_m'] . ' m' : '—' ?></td>
        <td data-label="Cert." class="st-muted"><?= e(CERT_LEVELS[$row['cert_required']] ?? '—') ?></td>
        <td data-label="Logged dives" class="st-num"><?= (int) ($usage[$row['id']] ?? 0) ?></td>
        <td data-label="Status"><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="st-pill <?= $row['is_published'] ? 'st-pill--ok' : 'st-pill--neutral' ?>" type="submit" style="border:0;cursor:pointer"><?= $row['is_published'] ? ui_icon('check', 'st-icon') . 'Visible' : 'Hidden' ?></button></form></td>
        <td data-label="" class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="/admin/catalog-sites.php?edit=<?= (int) $row['id'] ?>">Edit</a>
          <?php if (empty($usage[$row['id']])): ?><form method="post" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e(addslashes($row['name_en'])) ?>&quot;?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm st-btn--danger" type="submit" style="border-radius:var(--radius-pill)">Delete</button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php shell_end($currentUser);
