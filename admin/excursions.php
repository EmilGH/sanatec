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
            redirect('/admin/excursions.php' . ($id ? '?edit=' . $id : '?new=1'));
        }

        $savedId = excursion_save($input, $id ?: null);
        $prices  = implode(' / ', array_map(
            static fn (string $c): string => money(parse_money($input[$c])) ?? '—',
            array_keys(PRICE_COLUMNS)
        ));
        audit($id ? 'update' : 'create', 'excursion', $savedId, $input['name_en'] . ' — ' . $prices);
        flash($id ? 'Cenote route updated.' : 'Cenote route added.');
        og_invalidate();
        redirect('/admin/excursions.php');
    }

    if ($action === 'delete' && $id > 0) {
        $row = catalog_find('excursions', $id);
        catalog_delete('excursions', $id);
        audit('delete', 'excursion', $id, (string) ($row['name_en'] ?? ''));
        flash('Cenote route deleted.');
        redirect('/admin/excursions.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $live = catalog_toggle_published('excursions', $id);
        audit('publish', 'excursion', $id, $live ? 'shown on site' : 'hidden from site');
        flash($live ? 'Route is now visible on the site.' : 'Route hidden from the site.');
        redirect('/admin/excursions.php');
    }

    if ($action === 'move' && $id > 0) {
        $direction = (int) ($_POST['direction'] ?? 0) < 0 ? -1 : 1;
        catalog_move('excursions', $id, $direction);
        redirect('/admin/excursions.php');
    }

    redirect('/admin/excursions.php');
}

$editing = null;
if (isset($_GET['new'])) {
    $editing = ['is_published' => 1];
} elseif (isset($_GET['edit'])) {
    $editing = catalog_find('excursions', (int) $_GET['edit']);
    if ($editing === null) {
        flash('That route no longer exists.', 'warn');
        redirect('/admin/excursions.php');
    }
}

$excursions = catalog_all('excursions');

admin_header('Cenotes');
?>
<h1>Cenote adventures</h1>
<p class="lede">Prices are Mexican pesos. Leave a box blank when the route is not sold
  with that number of dives — the site shows a dash.</p>

<div class="card note">
  <strong>How the three price columns are read</strong>
  <p style="margin:8px 0 0" class="muted">Each column is the <strong>total price for a trip of that
  many dives</strong>, not the cost of one extra dive. So a route with 2&nbsp;dives at
  $3,900 means the whole two-dive day costs $3,900. This matches the printed guide, where
  Dreamgate has a two-dive price and no one-dive price. If your shop prices these
  differently, say so and the column headings can be changed.</p>
</div>

<?php if ($editing !== null): ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 style="margin-top:0"><?= isset($editing['id']) ? 'Edit route' : 'New route' ?></h2>

    <?php field_pair('name', 'Cenote or route name', $editing, 'text', 'Join multiple cenotes with +, e.g. Angelita + Carwash'); ?>

    <div class="row">
      <?php foreach (PRICE_COLUMNS as $column => $label) {
          field_price($column, 'Total for ' . $label, $editing);
      } ?>
    </div>

    <div style="margin-top:18px">
      <?php field_pair('cert', 'Certification required', $editing, 'text', 'e.g. OW, AOW, or a fuller sentence'); ?>
    </div>

    <label class="check" style="margin-bottom:10px">
      <input type="checkbox" name="is_special_price" value="1" <?= !empty($editing['is_special_price']) ? 'checked' : '' ?>>
      Mark the price with * and show the special-price footnote
    </label>
    <label class="check">
      <input type="checkbox" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>>
      Show this route on the public site
    </label>

    <div class="actions">
      <button class="btn primary" type="submit">Save route</button>
      <a class="btn" href="/admin/excursions.php">Cancel</a>
    </div>
  </form>
<?php else: ?>
  <p><a class="btn primary" href="/admin/excursions.php?new=1">Add a cenote route</a></p>
<?php endif; ?>

<div class="card" style="padding:0;overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th style="width:78px">Order</th>
        <th>Cenote / route</th>
        <?php foreach (PRICE_COLUMNS as $label): ?>
          <th class="num"><?= e($label) ?></th>
        <?php endforeach; ?>
        <th>Cert.</th>
        <th>Status</th>
        <th style="width:150px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($excursions as $i => $excursion): ?>
      <tr>
        <td class="order-cell">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="id" value="<?= (int) $excursion['id'] ?>">
            <button class="btn tiny" name="direction" value="-1" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
            <button class="btn tiny" name="direction" value="1" title="Move down" <?= $i === count($excursions) - 1 ? 'disabled' : '' ?>>↓</button>
          </form>
        </td>
        <td>
          <strong><?= e($excursion['name_en']) ?></strong>
          <?= $excursion['is_special_price'] ? ' <span class="pill">special price</span>' : '' ?>
        </td>
        <?php foreach (array_keys(PRICE_COLUMNS) as $column): ?>
          <td class="num"><?= e(money($excursion[$column]) ?? '—') ?></td>
        <?php endforeach; ?>
        <td class="muted"><?= e((string) $excursion['cert_en']) ?></td>
        <td>
          <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int) $excursion['id'] ?>">
            <button class="pill <?= $excursion['is_published'] ? 'live' : 'off' ?>" type="submit"
                    style="cursor:pointer;background-color:transparent;font:inherit;font-size:11px">
              <?= $excursion['is_published'] ? 'On the site' : 'Hidden' ?>
            </button>
          </form>
        </td>
        <td>
          <a class="btn tiny" href="/admin/excursions.php?edit=<?= (int) $excursion['id'] ?>">Edit</a>
          <form method="post" class="inline" onsubmit="return confirm('Delete &quot;<?= e(addslashes($excursion['name_en'])) ?>&quot;? This cannot be undone.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $excursion['id'] ?>">
            <button class="btn tiny danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
