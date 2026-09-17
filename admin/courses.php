<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';

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
            redirect('/admin/courses.php' . ($id ? '?edit=' . $id : '?new=1'));
        }

        $savedId = course_save($input, $id ?: null);
        $price   = money($input['price_mxn'] === '' ? null : parse_money($input['price_mxn'])) ?? 'Ask for pricing';
        audit($id ? 'update' : 'create', 'course', $savedId, $input['name_en'] . ' — ' . $price);
        flash($id ? 'Course updated.' : 'Course added.');
        redirect('/admin/courses.php');
    }

    if ($action === 'delete' && $id > 0) {
        $row = catalog_find('courses', $id);
        catalog_delete('courses', $id);
        audit('delete', 'course', $id, (string) ($row['name_en'] ?? ''));
        flash('Course deleted.');
        redirect('/admin/courses.php');
    }

    if ($action === 'toggle' && $id > 0) {
        $live = catalog_toggle_published('courses', $id);
        audit('publish', 'course', $id, $live ? 'shown on site' : 'hidden from site');
        flash($live ? 'Course is now visible on the site.' : 'Course hidden from the site.');
        redirect('/admin/courses.php');
    }

    if ($action === 'move' && $id > 0) {
        catalog_move('courses', $id, (int) ($_POST['direction'] ?? 0) < 0 ? -1 : 1);
        redirect('/admin/courses.php');
    }

    redirect('/admin/courses.php');
}

$editing = null;
if (isset($_GET['new'])) {
    $editing = ['is_published' => 1];
} elseif (isset($_GET['edit'])) {
    $editing = catalog_find('courses', (int) $_GET['edit']);
    if ($editing === null) {
        flash('That course no longer exists.', 'warn');
        redirect('/admin/courses.php');
    }
}

$courses = catalog_all('courses');

admin_header('Courses');
?>
<h1>Dive training</h1>
<p class="lede">Prices are Mexican pesos. Leave a price blank to show
  &ldquo;Ask for pricing&rdquo; instead of a number.</p>

<?php if ($editing !== null): ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 style="margin-top:0"><?= isset($editing['id']) ? 'Edit course' : 'New course' ?></h2>

    <?php field_pair('name', 'Course name', $editing); ?>

    <div class="row">
      <?php field_price('price_mxn', 'Price (MXN)', $editing, 'Blank = ask for pricing'); ?>
      <div>
        <label for="duration_en">Duration (English)</label>
        <input type="text" id="duration_en" name="duration_en" value="<?= e((string) ($editing['duration_en'] ?? '')) ?>" placeholder="3 days">
      </div>
      <div>
        <label for="duration_es">Duration (Español)</label>
        <input type="text" id="duration_es" name="duration_es" value="<?= e((string) ($editing['duration_es'] ?? '')) ?>" placeholder="3 días">
      </div>
    </div>

    <div style="margin-top:18px">
      <?php field_pair('note', 'Note (optional)', $editing, 'text', 'Shown after the course name. Leave blank for none.'); ?>
    </div>

    <label class="check">
      <input type="checkbox" name="is_published" value="1" <?= !empty($editing['is_published']) ? 'checked' : '' ?>>
      Show this course on the public site
    </label>

    <div class="actions">
      <button class="btn primary" type="submit">Save course</button>
      <a class="btn" href="/admin/courses.php">Cancel</a>
    </div>
  </form>
<?php else: ?>
  <p><a class="btn primary" href="/admin/courses.php?new=1">Add a course</a></p>
<?php endif; ?>

<div class="card" style="padding:0;overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th style="width:78px">Order</th>
        <th>Course</th>
        <th class="num">Price</th>
        <th>Duration</th>
        <th>Status</th>
        <th style="width:150px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($courses as $i => $course): ?>
      <tr>
        <td class="order-cell">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
            <button class="btn tiny" name="direction" value="-1" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
            <button class="btn tiny" name="direction" value="1" title="Move down" <?= $i === count($courses) - 1 ? 'disabled' : '' ?>>↓</button>
          </form>
        </td>
        <td>
          <strong><?= e($course['name_en']) ?></strong>
          <?php if (trim((string) $course['name_es']) !== '' && $course['name_es'] !== $course['name_en']): ?>
            <br><span class="muted"><?= e($course['name_es']) ?></span>
          <?php elseif (trim((string) $course['name_es']) === ''): ?>
            <br><span class="muted">No Spanish name yet</span>
          <?php endif; ?>
        </td>
        <td class="num"><?= e(money($course['price_mxn']) ?? 'Ask for pricing') ?></td>
        <td class="muted"><?= e((string) $course['duration_en']) ?></td>
        <td>
          <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
            <button class="pill <?= $course['is_published'] ? 'live' : 'off' ?>" type="submit"
                    style="cursor:pointer;background-color:transparent;font:inherit;font-size:11px"
                    title="<?= $course['is_published'] ? 'Hide from the site' : 'Show on the site' ?>">
              <?= $course['is_published'] ? 'On the site' : 'Hidden' ?>
            </button>
          </form>
        </td>
        <td>
          <a class="btn tiny" href="/admin/courses.php?edit=<?= (int) $course['id'] ?>">Edit</a>
          <form method="post" class="inline" onsubmit="return confirm('Delete &quot;<?= e(addslashes($course['name_en'])) ?>&quot;? This cannot be undone.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $course['id'] ?>">
            <button class="btn tiny danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_footer();
