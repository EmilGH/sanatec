<?php

declare(strict_types=1);

/**
 * The photo card on a catalogue item (course, cenote route, cenote): upload
 * with a 3:2 crop box, reorder, delete. Included by the catalogue pages.
 *
 *   photos_handle($action, $type, $itemId)  — call from the POST switch; true when it handled the action
 *   photos_card($type, $itemId, $postUrl)   — the card
 */

require_once __DIR__ . '/../src/Photos.php';

function photos_handle(string $action, string $type, int $itemId): bool
{
    switch ($action) {
        case 'photo_add':
            $crop = null;
            if (($_POST['crop_w'] ?? '') !== '') {
                $crop = [(int) $_POST['crop_x'], (int) $_POST['crop_y'], (int) $_POST['crop_w'], (int) $_POST['crop_h']];
            }
            $id = catalog_photo_add($type, $itemId, $_FILES['photo'] ?? [], $crop, post('caption_en'), post('caption_es'));
            audit('update', $type, $itemId, 'photo #' . $id . ' added');
            flash('Photo added.');
            return true;
        case 'photo_delete':
            catalog_photo_delete((int) ($_POST['photo_id'] ?? 0));
            flash('Photo removed.');
            return true;
        case 'photo_move':
            catalog_photo_move((int) ($_POST['photo_id'] ?? 0), (int) ($_POST['direction'] ?? 1));
            return true;
    }

    return false;
}

function photos_card(string $type, int $itemId, string $postUrl): void
{
    $photos = catalog_photos($type, $itemId);
    ?>
    <div class="st-card mb-4" id="photos">
      <div class="st-card__head"><h2 class="st-card__title">Photos</h2><span class="st-muted small">3:2 · shown on the item's page and in its preview card</span></div>
      <?php if ($photos !== []): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($photos as $i => $ph): ?>
        <figure class="m-0" style="width:180px">
          <img src="/photo/<?= (int) $ph['id'] ?>-s.jpg" alt="" style="width:100%;border-radius:var(--radius-sm);display:block">
          <figcaption class="small d-flex justify-content-between align-items-center mt-1">
            <form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="photo_move"><input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary" name="direction" value="-1" <?= $i === 0 ? 'disabled' : '' ?>>←</button>
              <button class="btn btn-sm btn-outline-secondary" name="direction" value="1" <?= $i === count($photos) - 1 ? 'disabled' : '' ?>>→</button></form>
            <span class="st-muted text-truncate px-1" style="max-width:80px"><?= e((string) ($ph['caption_en'] ?? '')) ?></span>
            <form method="post" onsubmit="return confirm('Remove this photo?')"><?= csrf_field() ?><input type="hidden" name="action" value="photo_delete"><input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>"><button class="btn btn-sm btn-outline-danger">×</button></form>
          </figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" id="photo-form"><?= csrf_field() ?><input type="hidden" name="action" value="photo_add">
        <input type="hidden" name="crop_x"><input type="hidden" name="crop_y"><input type="hidden" name="crop_w"><input type="hidden" name="crop_h">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-5"><label class="form-label small" for="photo-file">Add a photo</label><input class="form-control form-control-sm" type="file" id="photo-file" name="photo" accept="image/*" required></div>
          <div class="col-6 col-md-3"><label class="form-label small" for="caption_en">Caption <span class="text-aqua">EN</span></label><input class="form-control form-control-sm" id="caption_en" name="caption_en"></div>
          <div class="col-6 col-md-3"><label class="form-label small" for="caption_es">Caption <span class="text-aqua">ES</span></label><input class="form-control form-control-sm" id="caption_es" name="caption_es"></div>
          <div class="col-12 col-md-1"><button class="btn btn-sm btn-primary w-100" type="submit" id="photo-submit" disabled>Add</button></div>
        </div>
        <div id="crop-box" class="mt-3" hidden>
          <p class="st-muted small mb-1">Drag the box to choose what shows. It keeps the 3:2 shape.</p>
          <div style="max-height:420px"><img id="crop-img" alt="" style="max-width:100%;display:block"></div>
        </div>
      </form>
    </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
    <script>
    (function () {
      var file = document.getElementById('photo-file'), img = document.getElementById('crop-img'), box = document.getElementById('crop-box'),
          form = document.getElementById('photo-form'), submit = document.getElementById('photo-submit'), cropper = null;
      file.addEventListener('change', function () {
        if (!file.files[0]) { return; }
        if (cropper) { cropper.destroy(); cropper = null; }
        img.src = URL.createObjectURL(file.files[0]);
        box.hidden = false;
        img.onload = function () {
          cropper = new Cropper(img, { aspectRatio: 3 / 2, viewMode: 1, autoCropArea: 1, movable: false, zoomable: false, rotatable: false, scalable: false });
          submit.disabled = false;
        };
      });
      // Phone photos are 3–12 MB, more than the server accepts. The crop is
      // applied here and the result sent as a JPEG of at most 2400×1600, which
      // is well under a megabyte, so the upload is small and quick.
      form.addEventListener('submit', function (e) {
        if (!cropper || form.dataset.ready === '1') { return; }
        e.preventDefault();
        submit.disabled = true; submit.textContent = 'Sending…';
        var canvas = cropper.getCroppedCanvas({ maxWidth: 2400, maxHeight: 1600, imageSmoothingQuality: 'high' });
        canvas.toBlob(function (blob) {
          if (!blob || !window.DataTransfer) {           // old browser: send the original with the crop box
            var d = cropper.getData(true);
            form.crop_x.value = Math.max(0, d.x); form.crop_y.value = Math.max(0, d.y); form.crop_w.value = d.width; form.crop_h.value = d.height;
          } else {
            var dt = new DataTransfer();
            dt.items.add(new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
            file.files = dt.files;
            form.crop_x.value = form.crop_y.value = form.crop_w.value = form.crop_h.value = '';
          }
          form.dataset.ready = '1';
          form.submit();
        }, 'image/jpeg', 0.88);
      });
    })();
    </script>
    <?php
}
