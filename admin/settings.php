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

shell_start('Business info', $currentUser);
echo shell_page('Business info', 'Site content');
?>
<p class="st-lede mb-3">Everything on the public page that is not a course or a cenote price. Leave a Spanish box empty and the site falls back to the English text.</p>

<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($schema as $sectionKey => $section): ?>
    <div class="st-card mb-3" id="<?= e($sectionKey) ?>">
      <h2 class="st-card__title"><?= e($section['title']) ?></h2>
      <?php if (isset($section['intro'])): ?><p class="st-muted small"><?= e($section['intro']) ?></p><?php endif; ?>
      <?php foreach ($section['fields'] as $key => $field):
          $localized = $field['localized'] ?? true;
          $type      = $field['type'] ?? 'text';
          $help      = $field['help'] ?? '';
      ?>
        <?php if ($type === 'checkbox'): ?>
          <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= ($all[$key]['en'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($key) ?>"><?= e($field['label']) ?></label></div>
        <?php elseif ($localized): ?>
          <?php field_pair($key, $field['label'], $rowFor($key), $type, $help); ?>
        <?php else: ?>
          <div class="mb-3">
            <label class="form-label" for="<?= e($key) ?>"><?= e($field['label']) ?></label>
            <?php if ($type === 'textarea'): ?><textarea class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e($all[$key]['en'] ?? '') ?></textarea>
            <?php else: ?><input class="form-control" type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($all[$key]['en'] ?? '') ?>"><?php endif; ?>
            <?php if ($help !== ''): ?><div class="form-text"><?= e($help) ?></div><?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <div class="st-ctabar" style="grid-template-columns:auto auto;justify-content:start">
    <button class="btn btn-primary" type="submit">Save all changes</button>
    <a class="btn btn-outline-secondary" href="/" target="_blank" rel="noopener">View site</a>
  </div>
</form>
<?php shell_end($currentUser);
