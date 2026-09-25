<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Og.php';

$schema = settings_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $values = [];

    foreach ($schema as $section) {
        foreach ($section['fields'] as $key => $field) {
            $localized = $field['localized'] ?? true;
            $type      = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $values[$key] = ['en' => isset($_POST[$key]) ? '1' : '0', 'es' => null];
                continue;
            }

            if ($localized) {
                $values[$key] = ['en' => post($key . '_en'), 'es' => post($key . '_es')];
            } else {
                $values[$key] = ['en' => post($key), 'es' => null];
            }
        }
    }

    $changed = settings_save($values);
    if ($changed > 0) {
        og_invalidate();   // the home card carries the hero text and the phone number
    }

    if ($changed > 0) {
        audit('update', 'settings', '', $changed . ' field' . ($changed === 1 ? '' : 's') . ' changed');
        flash($changed === 1 ? 'One field saved.' : $changed . ' fields saved.');
    } else {
        flash('Nothing had changed.');
    }

    redirect('/admin/settings.php#' . (string) ($_POST['section'] ?? ''));
}

$all = settings_all();

/** Current stored values shaped the way field_pair() expects. */
$rowFor = static function (string $key) use ($all): array {
    return [
        $key . '_en' => $all[$key]['en'] ?? '',
        $key . '_es' => $all[$key]['es'] ?? '',
    ];
};

admin_header('Site content');
?>
<h1>Site content</h1>
<p class="lede">Everything on the public page that is not a course or a cenote price.
  Leave a Spanish box empty and the site falls back to the English text, so a
  half-finished translation never shows a blank page.</p>

<form method="post">
  <?= csrf_field() ?>

  <?php foreach ($schema as $sectionKey => $section): ?>
    <h2 id="<?= e($sectionKey) ?>"><?= e($section['title']) ?></h2>
    <?php if (isset($section['intro'])): ?>
      <p class="lede" style="margin-bottom:14px"><?= e($section['intro']) ?></p>
    <?php endif; ?>

    <div class="card">
      <?php foreach ($section['fields'] as $key => $field):
          $localized = $field['localized'] ?? true;
          $type      = $field['type'] ?? 'text';
          $help      = $field['help'] ?? '';
      ?>
        <?php if ($type === 'checkbox'): ?>
          <label class="check" style="margin-bottom:16px">
            <input type="checkbox" name="<?= e($key) ?>" value="1" <?= ($all[$key]['en'] ?? '0') === '1' ? 'checked' : '' ?>>
            <?= e($field['label']) ?>
          </label>
        <?php elseif ($localized): ?>
          <?php field_pair($key, $field['label'], $rowFor($key), $type, $help); ?>
        <?php else: ?>
          <div class="field">
            <label for="<?= e($key) ?>"><?= e($field['label']) ?></label>
            <?php if ($type === 'textarea'): ?>
              <textarea id="<?= e($key) ?>" name="<?= e($key) ?>"><?= e($all[$key]['en'] ?? '') ?></textarea>
            <?php else: ?>
              <input type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($all[$key]['en'] ?? '') ?>">
            <?php endif; ?>
            <?php if ($help !== ''): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="actions" style="position:sticky;bottom:0;background:#061e27;padding:14px 0;border-top:1px solid #274650">
    <button class="btn primary" type="submit">Save all changes</button>
    <a class="btn" href="/" target="_blank" rel="noopener">View site ↗</a>
  </div>
</form>
<?php admin_footer();
