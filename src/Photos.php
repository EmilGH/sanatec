<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Uploads.php';

/**
 * Photos on courses, cenote routes and cenotes: one aspect ratio, 3:2,
 * so every page lays out the same. The admin shows a crop box; the crop
 * (in the original's pixels) comes with the upload, and the server cuts
 * and resizes. Two files per photo: 1500×1000 and a 600×400 thumbnail.
 */

const PHOTO_W = 1500;
const PHOTO_H = 1000;
const PHOTO_THUMB_W = 600;
const PHOTO_TYPES = ['course' => 'courses', 'excursion' => 'excursions', 'dive_site' => 'dive_sites'];

function catalog_photos(string $type, int $itemId): array
{
    $stmt = db()->prepare('SELECT * FROM catalog_photos WHERE item_type = :t AND item_id = :i ORDER BY sort_order, id');
    $stmt->execute([':t' => $type, ':i' => $itemId]);

    return $stmt->fetchAll();
}

/** Every catalogue photo with the name of the item it belongs to, for pickers. */
function catalog_photos_all(): array
{
    return db()->query(
        'SELECT p.id, p.item_type, p.item_id, p.caption_en, p.path,
                COALESCE(c.name_en, x.name_en, s.name_en) AS item_name
         FROM catalog_photos p
         LEFT JOIN courses c ON p.item_type = "course" AND c.id = p.item_id
         LEFT JOIN excursions x ON p.item_type = "excursion" AND x.id = p.item_id
         LEFT JOIN dive_sites s ON p.item_type = "dive_site" AND s.id = p.item_id
         ORDER BY p.item_type, item_name, p.sort_order, p.id'
    )->fetchAll();
}

/** The photo beside the home page headline: the chosen one, else the first photo of a published excursion. */
function hero_photo(): ?array
{
    $id = (int) setting('hero_photo_id');
    if ($id > 0 && ($ph = catalog_photo_find($id)) !== null) {
        return $ph;
    }

    return db()->query(
        'SELECT p.* FROM catalog_photos p JOIN excursions x ON x.id = p.item_id AND x.is_published = 1
         WHERE p.item_type = "excursion" ORDER BY x.sort_order, x.id, p.sort_order, p.id LIMIT 1'
    )->fetch() ?: null;
}

function catalog_photo_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM catalog_photos WHERE id = :id');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

/** The first photo of an item, for cards and previews. */
function catalog_photo_first(string $type, int $itemId): ?array
{
    return catalog_photos($type, $itemId)[0] ?? null;
}

/**
 * Store an uploaded photo. $crop = [x, y, w, h] in the original's pixels
 * (after orientation); null means the largest centred 3:2 area.
 */
function catalog_photo_add(string $type, int $itemId, array $file, ?array $crop = null, string $captionEn = '', string $captionEs = ''): int
{
    if (!isset(PHOTO_TYPES[$type])) {
        throw new InvalidArgumentException('Unknown photo target.');
    }
    if (($msg = upload_error_message($file)) !== null) {
        throw new InvalidArgumentException($msg);
    }
    if ((int) $file['size'] > UPLOAD_MAX_BYTES) {
        throw new InvalidArgumentException('That photo is over 10 MB.');
    }
    $img = image_load_oriented((string) $file['tmp_name']);
    $w = imagesx($img);
    $h = imagesy($img);

    // The crop box, clamped to the image; fall back to a centred 3:2 area.
    if ($crop !== null && (int) ($crop[2] ?? 0) > 10 && (int) ($crop[3] ?? 0) > 10) {
        $cx = max(0, (int) $crop[0]); $cy = max(0, (int) $crop[1]);
        $cw = min($w - $cx, (int) $crop[2]); $ch = min($h - $cy, (int) $crop[3]);
    } else {
        if ($w / $h > PHOTO_W / PHOTO_H) {
            $ch = $h; $cw = (int) round($h * PHOTO_W / PHOTO_H);
        } else {
            $cw = $w; $ch = (int) round($w * PHOTO_H / PHOTO_W);
        }
        $cx = (int) (($w - $cw) / 2); $cy = (int) (($h - $ch) / 2);
    }
    // Keep the ratio exact whatever the box says.
    if ($cw / $ch > PHOTO_W / PHOTO_H) {
        $cw = (int) round($ch * PHOTO_W / PHOTO_H);
    } else {
        $ch = (int) round($cw * PHOTO_H / PHOTO_W);
    }

    $large = imagecreatetruecolor(PHOTO_W, PHOTO_H);
    imagecopyresampled($large, $img, 0, 0, $cx, $cy, PHOTO_W, PHOTO_H, $cw, $ch);
    $thumb = imagecreatetruecolor(PHOTO_THUMB_W, (int) (PHOTO_THUMB_W * PHOTO_H / PHOTO_W));
    imagecopyresampled($thumb, $large, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), PHOTO_W, PHOTO_H);
    imagedestroy($img);

    ob_start(); imagejpeg($large, null, 84); $largeBytes = (string) ob_get_clean();
    ob_start(); imagejpeg($thumb, null, 82); $thumbBytes = (string) ob_get_clean();
    imagedestroy($large);
    imagedestroy($thumb);

    $order = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM catalog_photos WHERE item_type = " . db()->quote($type) . " AND item_id = {$itemId}")->fetchColumn();
    db()->prepare('INSERT INTO catalog_photos (item_type, item_id, path, caption_en, caption_es, sort_order) VALUES (:t, :i, :p, :ce, :cs, :o)')
        ->execute([':t' => $type, ':i' => $itemId, ':p' => '', ':ce' => mb_substr(trim($captionEn), 0, 200) ?: null, ':cs' => mb_substr(trim($captionEs), 0, 200) ?: null, ':o' => $order]);
    $id = (int) db()->lastInsertId();
    $name = $type . '-' . $itemId . '-' . $id;
    $path = write_upload('catalog', $name . '.jpg', $largeBytes);
    write_upload('catalog', $name . '-s.jpg', $thumbBytes);
    db()->prepare('UPDATE catalog_photos SET path = :p WHERE id = :id')->execute([':p' => $path, ':id' => $id]);

    return $id;
}

function catalog_photo_delete(int $id): void
{
    $ph = catalog_photo_find($id);
    if ($ph === null) {
        return;
    }
    db()->prepare('DELETE FROM catalog_photos WHERE id = :id')->execute([':id' => $id]);
    foreach ([$ph['path'], preg_replace('/\.jpg$/', '-s.jpg', (string) $ph['path'])] as $rel) {
        $abs = upload_path((string) $rel);
        if ($abs !== null) {
            @unlink($abs);
        }
    }
}

function catalog_photo_move(int $id, int $direction): void
{
    $ph = catalog_photo_find($id);
    if ($ph === null) {
        return;
    }
    $all = catalog_photos((string) $ph['item_type'], (int) $ph['item_id']);
    $ids = array_map('intval', array_column($all, 'id'));
    $i = array_search($id, $ids, true);
    $j = $i + ($direction < 0 ? -1 : 1);
    if ($i === false || !isset($ids[$j])) {
        return;
    }
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    $upd = db()->prepare('UPDATE catalog_photos SET sort_order = :o WHERE id = :id');
    foreach ($ids as $o => $pid) {
        $upd->execute([':o' => $o + 1, ':id' => $pid]);
    }
}

/** The public URL of a photo (large) or its thumbnail. */
function photo_url(array $photo, bool $thumb = false): string
{
    return '/photo/' . (int) $photo['id'] . ($thumb ? '-s' : '') . '.jpg';
}
