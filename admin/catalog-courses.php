<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Og.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = post('action');
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $input = [
            'name_en'      => post('name_en'),
            'name_es'      => post('name_es'),
            'price_mxn'    => post('price_mxn'),
            'duration_en'  => post('duration_en'),
            'duration_es'  => post('duration_es'),
            'note_en'      => post('note_en'),
            'note_es'      => post('note_es'),
            'is_published' => isset($_POST['is_published']),
        ];

        if ($input['name_en'] === '') {
            flash('A course needs an English name.', 'warn');
            redirect('/admin/catalog-courses.php' . ($id ? '?edit=' . $id : '?new=1'));
        }

        $savedId = course_save($input, $id ?: null);
        $price   = money($input['price_mxn'] === '' ? null : parse_money($input['price_mxn'])) ?? 'Ask for pricing';
        audit($id ? 'update' : 'create', 'course', $savedId, $input['name_en'] . ' — ' . $price);
        flash($id ? 'Course updated.' : 'Course added.');
        og_invalidate();
        redirect('/admin/catalog-courses.php');
    }

    if ($action === 'delete' && $id > 0) {
        $row = catalog_find('courses', $id);
        catalog_delete('courses', $id);
        audit('delete', 'course', $id, (string) ($row['name_en'] ?? ''));
        flash('Course deleted.');
        redirect('/admin/catalog-courses.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $live = catalog_toggle_published('courses', $id);
        audit('publish', 'course', $id, $live ? 'shown on site' : 'hidden from site');
        flash($live ? 'Course is now visible on the site.' : 'Course hidden from the site.');
        redirect('/admin/catalog-courses.php');
    }

    if ($action === 'move' && $id > 0) {
        catalog_move('courses', $id, (int) ($_POST['direction'] ?? 0) < 0 ? -1 : 1);
        redirect('/admin/catalog-courses.php');
    }

    redirect('/admin/catalog-courses.php');
}

$editing = null;
if (isset($_GET['new'])) {
    $editing = ['is_published' => 1];
} elseif (isset($_GET['edit'])) {
    $editing = catalog_find('courses', (int) $_GET['edit']);
    if ($editing === null) {
        flash('That course no longer exists.', 'warn');
        redirect('/admin/catalog-courses.php');
    }
}

shell_start('Dive training', $currentUser);
echo shell_page('Dive training', 'Catalog', '<a class="btn btn-primary" href="/admin/catalog-courses.php?new=1">' . ui_icon('plus') . 'Add</a>');
?>
<p class="st-lede mb-3"><?= e('Prices are Mexican pesos. Leave a price blank to show “Ask us” instead of a number.') ?></p>
<div class="st-tabs mb-3">
  <a href="/admin/catalog-courses.php" <?= 'courses' === 'courses' ? 'aria-current="page"' : '' ?>>Courses</a>
  <a href="/admin/catalog-excursions.php" <?= 'courses' === 'excursions' ? 'aria-current="page"' : '' ?>>Cenote excursions</a>
  <a href="/admin/catalog-sites.php">Dive sites</a>
</div>

<?php if ($editing !== null): ?>
  <form method="post" class="st-card mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 class="st-card__title mb-3"><?= isset($editing['id']) ? 'Edit' : 'New' ?> course</h2>
    <?php field_pair('name', 'Course name', $editing); ?>
    <div class="row g-3">
      <div class="col-6 col-md-3"><?php field_price('price_mxn', 'Price (MXN)', $editing, 'Blank = ask us'); ?></div>
      <div class="col-6 col-md-3"><label class="form-label" for="duration_en">Duration (English)</label><input class="form-control" id="duration_en" name="duration_en" value="<?= e((string) ($editing['duration_en'] ?? '')) ?>" placeholder="3 days"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="duration_es">Duration (Español)</label><input class="form-control" id="duration_es" name="duration_es" value="<?= e((string) ($editing['duration_es'] ?? '')) ?>" placeholder="3 días"></div>
    </div>
    <div class="mt-3"><?php field_pair('note', 'Note (optional)', $editing, 'text', 'Shown under the course name.'); ?></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>><label class="form-check-label" for="is_published">Show this course on the public site</label></div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-outline-secondary" href="/admin/catalog-courses.php">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<div class="st-card" style="padding:0 16px">
  <div class="st-tablewrap"><table class="st-table">
    <thead><tr><th style="width:84px">Order</th><th>Course</th><th class="text-end">Price</th><th>Duration</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php $rows = catalog_all('courses'); foreach ($rows as $i => $row): ?>
      <tr>
        <td data-label="Order" class="order-cell">
          <form method="post" style="display:inline-flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary" name="direction" value="-1" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
            <button class="btn btn-sm btn-outline-secondary" name="direction" value="1" title="Move down" <?= $i === count($rows) - 1 ? 'disabled' : '' ?>>↓</button></form>
        </td>
          <td data-label="Course" class="st-table__main"><strong><?= e($row['name_en']) ?></strong><?= trim((string) $row['name_es']) === '' ? ' <span class="st-pill st-pill--warn">no Spanish</span>' : '' ?></td>
          <td data-label="Price" class="text-md-end st-num"><?= e(money($row['price_mxn']) ?? 'Ask us') ?></td>
          <td data-label="Duration" class="st-muted"><?= e((string) $row['duration_en']) ?></td>
        <td data-label="Status">
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="st-pill <?= $row['is_published'] ? 'st-pill--ok' : 'st-pill--neutral' ?>" type="submit" style="border:0;cursor:pointer" title="<?= $row['is_published'] ? 'Hide from the site' : 'Show on the site' ?>"><?= $row['is_published'] ? ui_icon('check', 'st-icon') . 'On the site' : 'Hidden' ?></button></form>
        </td>
        <td data-label="" class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="/admin/catalog-courses.php?edit=<?= (int) $row['id'] ?>">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e(addslashes($row['name_en'])) ?>&quot;? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <button class="btn btn-sm st-btn--danger" type="submit" style="border-radius:var(--radius-pill)">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php shell_end($currentUser);
