<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Og.php';

const PRICE_COLUMNS = ['price_1_dive' => '1 dive', 'price_2_dives' => '2 dives', 'price_3_dives' => '3 dives'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = post('action');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $input = [
            'name_en'          => post('name_en'),
            'name_es'          => post('name_es'),
            'price_1_dive'     => post('price_1_dive'),
            'price_2_dives'    => post('price_2_dives'),
            'price_3_dives'    => post('price_3_dives'),
            'cert_en'          => post('cert_en'),
            'cert_es'          => post('cert_es'),
            'is_special_price' => isset($_POST['is_special_price']),
            'is_published'     => isset($_POST['is_published']),
        ];

        if ($input['name_en'] === '') {
            flash('A cenote route needs an English name.', 'warn');
            redirect('/admin/catalog-excursions.php' . ($id ? '?edit=' . $id : '?new=1'));
        }

        $savedId = excursion_save($input, $id ?: null);
        $prices  = implode(' / ', array_map(
            static fn (string $c): string => money(parse_money($input[$c])) ?? '—',
            array_keys(PRICE_COLUMNS)
        ));
        audit($id ? 'update' : 'create', 'excursion', $savedId, $input['name_en'] . ' — ' . $prices);
        flash($id ? 'Cenote route updated.' : 'Cenote route added.');
        og_invalidate();
        redirect('/admin/catalog-excursions.php');
    }

    if ($action === 'delete' && $id > 0) {
        $row = catalog_find('excursions', $id);
        catalog_delete('excursions', $id);
        audit('delete', 'excursion', $id, (string) ($row['name_en'] ?? ''));
        flash('Cenote route deleted.');
        redirect('/admin/catalog-excursions.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $live = catalog_toggle_published('excursions', $id);
        audit('publish', 'excursion', $id, $live ? 'shown on site' : 'hidden from site');
        flash($live ? 'Route is now visible on the site.' : 'Route hidden from the site.');
        redirect('/admin/catalog-excursions.php');
    }

    if ($action === 'move' && $id > 0) {
        $direction = (int) ($_POST['direction'] ?? 0) < 0 ? -1 : 1;
        catalog_move('excursions', $id, $direction);
        redirect('/admin/catalog-excursions.php');
    }

    redirect('/admin/catalog-excursions.php');
}

$editing = null;
if (isset($_GET['new'])) {
    $editing = ['is_published' => 1];
} elseif (isset($_GET['edit'])) {
    $editing = catalog_find('excursions', (int) $_GET['edit']);
    if ($editing === null) {
        flash('That route no longer exists.', 'warn');
        redirect('/admin/catalog-excursions.php');
    }
}

shell_start('Cenote excursions', $currentUser);
echo shell_page('Cenote excursions', 'Catalog', '<a class="btn btn-primary" href="/admin/catalog-excursions.php?new=1">' . ui_icon('plus') . 'Add</a>');
?>
<p class="st-lede mb-3"><?= e('Prices are Mexican pesos. Each column is the total for that many dives; leave a box blank when the route is not sold that way.') ?></p>
<div class="st-tabs mb-3">
  <a href="/admin/catalog-courses.php" <?= 'excursions' === 'courses' ? 'aria-current="page"' : '' ?>>Courses</a>
  <a href="/admin/catalog-excursions.php" <?= 'excursions' === 'excursions' ? 'aria-current="page"' : '' ?>>Cenote excursions</a>
</div>

<?php if ($editing !== null): ?>
  <form method="post" class="st-card mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 class="st-card__title mb-3"><?= isset($editing['id']) ? 'Edit' : 'New' ?> excursion</h2>
    <?php field_pair('name', 'Cenote or route name', $editing, 'text', 'Join cenotes with +, e.g. Angelita + Carwash'); ?>
    <div class="row g-3">
      <?php foreach (PRICE_COLUMNS as $column => $label): ?><div class="col-4 col-md-3"><?php field_price($column, 'Total for ' . $label, $editing); ?></div><?php endforeach; ?>
    </div>
    <div class="mt-3"><?php field_pair('cert', 'Certification required', $editing, 'text', 'OW, AOW, or a fuller sentence'); ?></div>
    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="is_special_price" name="is_special_price" value="1" <?= !empty($editing['is_special_price']) ? 'checked' : '' ?>><label class="form-check-label" for="is_special_price">Mark as a special price</label></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>><label class="form-check-label" for="is_published">Show this excursion on the public site</label></div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-outline-secondary" href="/admin/catalog-excursions.php">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<div class="st-card" style="padding:0 16px">
  <div class="st-tablewrap"><table class="st-table">
    <thead><tr><th style="width:84px">Order</th><th>Cenote / route</th><?php foreach (PRICE_COLUMNS as $label): ?><th class="text-end"><?= e($label) ?></th><?php endforeach; ?><th>Cert.</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php $rows = catalog_all('excursions'); foreach ($rows as $i => $row): ?>
      <tr>
        <td data-label="Order" class="order-cell">
          <form method="post" style="display:inline-flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary" name="direction" value="-1" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
            <button class="btn btn-sm btn-outline-secondary" name="direction" value="1" title="Move down" <?= $i === count($rows) - 1 ? 'disabled' : '' ?>>↓</button></form>
        </td>
          <td data-label="Route" class="st-table__main"><strong><?= e($row['name_en']) ?></strong><?= $row['is_special_price'] ? ' <span class="st-pill st-pill--warn">special</span>' : '' ?></td>
          <?php foreach (array_keys(PRICE_COLUMNS) as $column): ?><td data-label="<?= e(PRICE_COLUMNS[$column]) ?>" class="text-md-end st-num"><?= e(money($row[$column]) ?? '—') ?></td><?php endforeach; ?>
          <td data-label="Cert." class="st-muted"><?= e((string) $row['cert_en']) ?></td>
        <td data-label="Status">
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="st-pill <?= $row['is_published'] ? 'st-pill--ok' : 'st-pill--neutral' ?>" type="submit" style="border:0;cursor:pointer" title="<?= $row['is_published'] ? 'Hide from the site' : 'Show on the site' ?>"><?= $row['is_published'] ? ui_icon('check', 'st-icon') . 'On the site' : 'Hidden' ?></button></form>
        </td>
        <td data-label="" class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="/admin/catalog-excursions.php?edit=<?= (int) $row['id'] ?>">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e(addslashes($row['name_en'])) ?>&quot;? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-sm st-btn--danger" type="submit" style="border-radius:var(--radius-pill)">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php shell_end($currentUser);
