<?php

declare(strict_types=1);

/** The diver's CENOTE Exploration Passport: stamps, dives, photos, wish list. */

require __DIR__ . '/_init.php';
require_once __DIR__ . '/../src/Passport.php';

$cid = (int) $customer['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        switch (post('action')) {
            case 'wish':
                wishlist_toggle($cid, (int) ($_POST['site_id'] ?? 0));
                break;
            case 'public':
                db()->prepare('UPDATE customers SET passport_public = :p WHERE id = :c')->execute([':p' => isset($_POST['passport_public']) ? 1 : 0, ':c' => $cid]);
                flash(isset($_POST['passport_public']) ? tr('Your passport is public.', 'Tu pasaporte es público.') : tr('Your passport is private again.', 'Tu pasaporte vuelve a ser privado.'));
                break;
            case 'dive_notes':
                if (customer_dive($cid, (int) ($_POST['dive_id'] ?? 0)) !== null) {
                    dive_update_notes($cid, (int) $_POST['dive_id'], $_POST);
                    flash(tr('Saved.', 'Guardado.'));
                }
                break;
            case 'photo_add':
                if (customer_dive($cid, (int) ($_POST['dive_id'] ?? 0)) !== null) {
                    foreach (array_keys($_FILES['photos']['name'] ?? []) as $i) {
                        $f = ['name' => $_FILES['photos']['name'][$i], 'tmp_name' => $_FILES['photos']['tmp_name'][$i], 'error' => $_FILES['photos']['error'][$i], 'size' => $_FILES['photos']['size'][$i]];
                        if ($f['error'] === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        dive_photo_add((int) $_POST['dive_id'], $f, (int) $currentUser['id']);
                    }
                    flash(tr('Photos added.', 'Fotos añadidas.'));
                }
                break;
            case 'photo_public':
                dive_photo_set_public((int) ($_POST['photo_id'] ?? 0), $cid, !empty($_POST['is_public']));
                break;
            case 'photo_delete':
                dive_photo_delete((int) ($_POST['photo_id'] ?? 0), $cid);
                flash(tr('Photo removed.', 'Foto eliminada.'));
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
    }
    redirect('/my/passport.php' . (isset($_POST['dive_id']) ? '#dive-' . (int) $_POST['dive_id'] : ''));
}

$dives = customer_dives($cid);
$stamps = customer_stamps($cid);
$wish = customer_wishlist($cid);
$sites = dive_sites();
$stamped = array_column($stamps, 'id');
$isPublic = (int) customer_find($cid)['passport_public'] === 1;
$shareUrl = rtrim((string) cfg('base_url'), '/') . '/passport/' . $currentUser['public_id'];

shell_start(tr('My passport', 'Mi pasaporte'), $currentUser, 'diver', ['back' => '/my/']);
?>
<p class="st-eyebrow"><?= e(tr('CENOTE Exploration Passport', 'Pasaporte de exploración de cenotes')) ?></p>
<h1 class="st-h1 mb-3"><?= e($currentUser['name']) ?></h1>

<div class="st-stats mb-4">
  <div class="st-stat"><b><?= count($dives) ?></b><span><?= e(tr('cenote dives', 'buceos en cenote')) ?></span></div>
  <div class="st-stat"><b><?= count($stamps) ?></b><span><?= e(tr('cenotes explored', 'cenotes explorados')) ?></span></div>
</div>

<div class="st-card mb-3">
  <h2 class="st-card__title mb-2"><?= e(tr('Stamps', 'Sellos')) ?></h2>
  <?php if ($stamps === []): ?><p class="st-muted mb-0"><?= e(tr('Your first cenote goes here.', 'Tu primer cenote irá aquí.')) ?></p><?php else: ?>
  <div class="st-chips">
    <?php foreach ($stamps as $s): ?><span class="st-chip st-chip--on" title="<?= e(tr('first', 'primera vez') . ' ' . $s['first_on']) ?>"><?= ui_icon('check', 'st-icon') ?><?= e($lang === 'es' ? $s['name_es'] : $s['name_en']) ?><?= (int) $s['dives'] > 1 ? ' ×' . (int) $s['dives'] : '' ?></span><?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="st-card mb-3">
  <h2 class="st-card__title mb-1"><?= e(tr('Still to explore', 'Por explorar')) ?></h2>
  <p class="st-muted small"><?= e(tr('Tap a cenote to add it to your wish list.', 'Toca un cenote para añadirlo a tu lista de deseos.')) ?></p>
  <div class="st-chips">
    <?php foreach ($sites as $s): if (in_array((int) $s['id'], $stamped, true)) continue; $on = in_array((int) $s['id'], $wish, true); ?>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="wish"><input type="hidden" name="site_id" value="<?= (int) $s['id'] ?>">
        <button class="st-chip <?= $on ? 'st-chip--on' : '' ?>" type="submit"><?= $on ? '★ ' : '' ?><?= e($lang === 'es' ? $s['name_es'] : $s['name_en']) ?></button></form>
    <?php endforeach; ?>
  </div>
</div>

<h2 class="st-h3 mt-4 mb-2"><?= e(tr('Dive log', 'Bitácora')) ?></h2>
<?php if ($dives === []): ?><p class="st-muted"><?= e(tr('Dives are added here after each trip.', 'Los buceos se añaden aquí después de cada salida.')) ?></p><?php endif; ?>
<?php foreach ($dives as $i => $d): $n = count($dives) - $i; $photos = dive_photos((int) $d['id']); ?>
<div class="st-card mb-3" id="dive-<?= (int) $d['id'] ?>">
  <div class="st-card__head">
    <div><span class="st-muted small">#<?= $n ?> · <?= e(date('j M Y', strtotime($d['dived_on']))) ?></span>
      <h3 class="st-h2 mb-0"><?= e($lang === 'es' ? $d['site_es'] : $d['site_en']) ?></h3>
      <span class="st-muted small"><?= $d['guide_name'] ? e(tr('with', 'con') . ' ' . $d['guide_name']) : '' ?><?= $d['event_title'] ? ' · ' . e($d['event_title']) : '' ?></span></div>
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-<?= (int) $d['id'] ?>"><?= ui_icon('pen', 'st-icon') ?><?= e(tr('Notes', 'Notas')) ?></button>
  </div>
  <?php if ($d['notes'] || $d['max_depth_m'] || $d['duration_min']): ?>
    <p class="mb-2"><?= $d['max_depth_m'] ? e($d['max_depth_m']) . ' m' : '' ?><?= $d['duration_min'] ? ' · ' . (int) $d['duration_min'] . ' min' : '' ?><?= $d['notes'] ? '<br>' . nl2br(e($d['notes'])) : '' ?></p>
  <?php endif; ?>
  <form method="post" class="collapse mb-2" id="edit-<?= (int) $d['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="dive_notes"><input type="hidden" name="dive_id" value="<?= (int) $d['id'] ?>">
    <div class="row g-2">
      <div class="col-4"><input class="form-control form-control-sm" name="max_depth_m" value="<?= e((string) $d['max_depth_m']) ?>" placeholder="<?= e(tr('max m', 'máx m')) ?>" inputmode="decimal"></div>
      <div class="col-4"><input class="form-control form-control-sm" name="duration_min" value="<?= e((string) $d['duration_min']) ?>" placeholder="min" inputmode="numeric"></div>
      <div class="col-12"><textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="<?= e(tr('What you saw, how it felt', 'Lo que viste, cómo te sentiste')) ?>"><?= e((string) $d['notes']) ?></textarea></div>
      <div class="col-12"><button class="btn btn-sm btn-primary" type="submit"><?= e(tr('Save', 'Guardar')) ?></button></div>
    </div></form>
  <?php if ($photos !== []): ?>
  <div class="d-flex flex-wrap gap-2 mb-2">
    <?php foreach ($photos as $ph): ?>
    <figure class="m-0" style="width:calc(50% - 4px)">
      <img src="/my/photo.php?id=<?= (int) $ph['id'] ?>" alt="<?= e((string) $ph['caption']) ?>" style="width:100%;border-radius:var(--radius-sm);display:block" loading="lazy">
      <figcaption class="small d-flex justify-content-between align-items-center mt-1">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="photo_public"><input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>"><input type="hidden" name="dive_id" value="<?= (int) $d['id'] ?>">
          <label class="st-switch" style="min-height:32px"><input type="checkbox" name="is_public" value="1" <?= $ph['is_public'] ? 'checked' : '' ?> onchange="this.form.submit()"><i></i><span class="small"><?= e(tr('public', 'pública')) ?></span></label></form>
        <form method="post" onsubmit="return confirm('<?= e(tr('Remove this photo?', '¿Eliminar esta foto?')) ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="photo_delete"><input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>"><input type="hidden" name="dive_id" value="<?= (int) $d['id'] ?>"><button class="st-iconbtn" style="width:32px;height:32px"><?= ui_icon('x', 'st-icon st-muted') ?></button></form>
      </figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="photo_add"><input type="hidden" name="dive_id" value="<?= (int) $d['id'] ?>">
    <label class="st-upload" for="photos-<?= (int) $d['id'] ?>"><?= ui_icon('upload') ?><span><b><?= e(tr('Add photos', 'Añadir fotos')) ?></b><br><?= e(tr('They stay private unless you switch one to public.', 'Se quedan privadas salvo que marques alguna como pública.')) ?></span></label>
    <input class="visually-hidden" type="file" id="photos-<?= (int) $d['id'] ?>" name="photos[]" accept="image/*" multiple onchange="this.form.submit()">
  </form>
</div>
<?php endforeach; ?>

<div class="st-card mb-4">
  <h2 class="st-card__title mb-1"><?= e(tr('Share your passport', 'Comparte tu pasaporte')) ?></h2>
  <p class="st-muted small"><?= e(tr('A public page with your stamps, your dive count, and only the photos you mark public. Off by default.', 'Una página pública con tus sellos, tu número de buceos y solo las fotos que marques públicas. Desactivado por defecto.')) ?></p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="public">
    <label class="st-switch"><input type="checkbox" name="passport_public" value="1" <?= $isPublic ? 'checked' : '' ?> onchange="this.form.submit()"><i></i><span><?= e(tr('Public passport', 'Pasaporte público')) ?></span></label></form>
  <?php if ($isPublic): ?><p class="mt-2 mb-0 small"><a class="st-link" href="<?= e($shareUrl) ?>" target="_blank"><?= e($shareUrl) ?></a></p><?php endif; ?>
</div>
<?php shell_end($currentUser, 'diver');
