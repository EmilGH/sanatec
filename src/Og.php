<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Uploads.php';

/**
 * Link-preview images — 1200×630 PNGs for the home page and for every course
 * and excursion, per language. Adapted from the Tide Line handoff; GD only.
 *
 * Rendered on first request and cached under the private uploads directory;
 * saving a catalogue row in the admin drops that row's cached files.
 * Fonts are SIL OFL (licences alongside) and ship with the repository.
 */

const OG_W = 1200;
const OG_H = 630;
const OG_BG = [4, 38, 58];
const OG_INK = [241, 248, 247];
const OG_MUTED = [169, 194, 200];
const OG_AQUA = [85, 220, 224];
const OG_SHAFT = [168, 252, 255];
const OG_SWOOSH = [14, 183, 206];
const OG_WARN = [242, 193, 78];

const OG_T = [
    'en' => ['course' => 'Dive course', 'excursion' => 'Cenote dive', 'dive' => '1 dive', 'dives' => '%d dives', 'ask' => 'Ask us for the price',
             'special' => 'Special price', 'mxn' => 'MXN per diver', 'course_mxn' => 'MXN', 'home' => 'Tulum · Cenotes · PADI & TDI'],
    'es' => ['course' => 'Curso de buceo', 'excursion' => 'Buceo en cenote', 'dive' => '1 inmersión', 'dives' => '%d inmersiones', 'ask' => 'Pregúntanos el precio',
             'special' => 'Precio especial', 'mxn' => 'MXN por buzo', 'course_mxn' => 'MXN', 'home' => 'Tulum · Cenotes · PADI y TDI'],
];

function og_assets(): string { return SANATEC_ROOT . '/assets/og'; }
function og_font(string $name): string { return og_assets() . "/fonts/{$name}.ttf"; }
function og_cache_dir(): string { return uploads_dir() . '/og'; }
function og_col(GdImage $im, array $rgb, int $alpha = 0): int { return (int) imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha); }
function og_money(float $v): string { return '$' . number_format($v, 0, '.', ','); }

function og_text(GdImage $im, float $size, int $x, int $y, int $color, string $font, string $text, float $track = 0.0): int
{
    if ($track == 0.0) {
        $b = imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        return $b[2] - $b[0];
    }
    $cx = (float) $x;
    foreach (mb_str_split($text) as $ch) {
        imagettftext($im, $size, 0, (int) $cx, $y, $color, $font, $ch);
        $adv = imagettfbbox($size, 0, $font, $ch);
        $cx += ($adv[2] - $adv[0]) + $track * $size;
        if ($ch === ' ') {
            $cx += $size * 0.28;
        }
    }
    return (int) ($cx - $x);
}

function og_width(float $size, string $font, string $text): int
{
    $b = imagettfbbox($size, 0, $font, $text);
    return $b[2] - $b[0];
}

function og_wrap(float $size, string $font, string $text, int $max): array
{
    $lines = [];
    $cur = '';
    foreach (preg_split('/\s+/', trim($text)) ?: [] as $w) {
        $try = $cur === '' ? $w : "{$cur} {$w}";
        if (og_width($size, $font, $try) <= $max || $cur === '') {
            $cur = $try;
        } else {
            $lines[] = $cur;
            $cur = $w;
        }
    }
    if ($cur !== '') {
        $lines[] = $cur;
    }
    return $lines;
}

function og_wave(GdImage $im, int $y, array $rgb, int $alpha, int $thick): void
{
    $c = og_col($im, $rgb, $alpha);
    imagesetthickness($im, $thick);
    $prev = null;
    for ($x = -10; $x <= OG_W + 10; $x += 6) {
        $yy = (int) ($y + 18 * sin(($x / OG_W) * 2 * M_PI * 1.15 + 0.6));
        if ($prev !== null) {
            imageline($im, $prev[0], $prev[1], $x, $yy, $c);
        }
        $prev = [$x, $yy];
    }
    imagesetthickness($im, 1);
}

/** The shared canvas: background, waves, roundel left, wordmark top right. Returns [$im, $x0, $maxw]. */
function og_canvas(): array
{
    $im = imagecreatetruecolor(OG_W, OG_H);
    imagealphablending($im, true);
    imagesavealpha($im, false);
    imagefill($im, 0, 0, og_col($im, OG_BG));
    og_wave($im, 560, OG_SWOOSH, 80, 4);
    og_wave($im, 590, OG_SWOOSH, 105, 2);

    $r = imagecreatefrompng(og_assets() . '/roundel-840.png');
    imagecopyresampled($im, $r, 56, 95, 0, 0, 420, 420, imagesx($r), imagesy($r));
    imagedestroy($r);

    $x0 = 540;
    $wm = imagecreatefrompng(og_assets() . '/wordmark-on-dark.png');
    $ww = 300;
    $wh = (int) round(imagesy($wm) * $ww / imagesx($wm));
    imagecopyresampled($im, $wm, $x0, 64, 0, 0, $ww, $wh, imagesx($wm), imagesy($wm));
    imagedestroy($wm);

    return [$im, $x0, OG_W - $x0 - 60];
}

/**
 * $item: ['type' => 'course'|'excursion', 'name' => string,
 *         course:    'price' => ?float, 'duration' => string
 *         excursion: 'prices' => [?float, ?float, ?float], 'cert' => string, 'special' => bool]
 */
function og_render_item(array $item, string $lang): GdImage
{
    $t = OG_T[$lang] ?? OG_T['en'];
    [$im, $x0, $maxw] = og_canvas();
    $sans = og_font('Outfit-Medium');
    $bold = og_font('Outfit-Bold');
    $serif = og_font('Fraunces-SoftMedium');

    $eyebrow = mb_strtoupper($item['type'] === 'course' ? $t['course'] . ' · ' . $item['duration'] : $t['excursion'] . ' · ' . ($item['cert'] ?? ''));
    og_text($im, 15, $x0, 206, og_col($im, OG_SHAFT), $bold, $eyebrow, 0.14);

    $size = 50;
    do {
        $lines = og_wrap($size, $serif, $item['name'], $maxw);
        $size -= 4;
    } while (count($lines) > 2 && $size > 34);
    $size += 4;
    $y = 206 + 22 + (int) ($size * 1.15);
    foreach ($lines as $ln) {
        imagettftext($im, $size, 0, $x0, $y, og_col($im, OG_INK), $serif, $ln);
        $y += (int) ($size * 1.3);
    }

    $y += 14;
    if ($item['type'] === 'course') {
        if ($item['price']) {
            $p = og_money($item['price']);
            imagettftext($im, 34, 0, $x0, $y + 12, og_col($im, OG_AQUA), $serif, $p);
            og_text($im, 17, $x0 + og_width(34, $serif, $p) + 14, $y + 10, og_col($im, OG_MUTED), $sans, $t['course_mxn']);
        } else {
            imagettftext($im, 24, 0, $x0, $y + 8, og_col($im, OG_AQUA), $bold, $t['ask']);
        }
    } else {
        $cx = $x0;
        foreach ($item['prices'] as $i => $p) {
            if ($p === null) {
                continue;
            }
            $q = $i === 0 ? $t['dive'] : sprintf($t['dives'], $i + 1);
            og_text($im, 16, (int) $cx, $y - 26, og_col($im, OG_MUTED), $sans, $q);
            imagettftext($im, 32, 0, (int) $cx, $y + 20, og_col($im, OG_AQUA), $serif, og_money($p));
            $cx += max(og_width(32, $serif, og_money($p)), og_width(16, $sans, $q)) + 40;
        }
        og_text($im, 15, $x0, $y + 56, og_col($im, $item['special'] ? OG_WARN : OG_MUTED), $sans,
            $item['special'] ? $t['special'] . ' · ' . $t['mxn'] : $t['mxn']);
    }

    og_text($im, 16, $x0, 540, og_col($im, OG_MUTED), $sans, 'sanatecdiving.com  ·  WhatsApp ' . setting('phone_display'));
    return $im;
}

/** The home card: kicker, headline, tagline. */
function og_render_home(string $lang): GdImage
{
    $t = OG_T[$lang] ?? OG_T['en'];
    [$im, $x0, $maxw] = og_canvas();
    $sans = og_font('Outfit-Medium');
    $bold = og_font('Outfit-Bold');
    $serif = og_font('Fraunces-SoftMedium');

    og_text($im, 15, $x0, 206, og_col($im, OG_SHAFT), $bold, mb_strtoupper($t['home']), 0.14);
    $y = 290;
    foreach (og_wrap(46, $serif, setting('hero_title_a', $lang), $maxw) as $ln) {
        imagettftext($im, 46, 0, $x0, $y, og_col($im, OG_INK), $serif, $ln);
        $y += 58;
    }
    foreach (og_wrap(46, $serif, setting('hero_title_b', $lang), $maxw) as $ln) {
        imagettftext($im, 46, 0, $x0, $y, og_col($im, OG_AQUA), $serif, $ln);
        $y += 58;
    }
    $y += 6;
    foreach (og_wrap(19, $sans, setting('hero_intro', $lang), $maxw) as $i => $ln) {
        if ($i > 2) {
            break;
        }
        og_text($im, 19, $x0, $y, og_col($im, OG_MUTED), $sans, $ln);
        $y += 28;
    }
    og_text($im, 16, $x0, 540, og_col($im, OG_MUTED), $sans, 'sanatecdiving.com  ·  WhatsApp ' . setting('phone_display'));
    return $im;
}

function og_item_from_row(string $type, array $row, string $lang): array
{
    $l = $lang === 'es' ? 'es' : 'en';
    $name = trim((string) ($row["name_{$l}"] ?? '')) ?: (string) $row['name_en'];
    if ($type === 'course') {
        return ['type' => 'course', 'name' => $name, 'price' => $row['price_mxn'] !== null ? (float) $row['price_mxn'] : null,
                'duration' => trim((string) ($row["duration_{$l}"] ?? '')) ?: (string) $row['duration_en']];
    }
    $cert = trim((string) ($row["cert_{$l}"] ?? '')) ?: (string) $row['cert_en'];
    if (stripos($cert, 'open water') === 0) {
        $cert = 'OW';
    }
    $num = static fn ($v): ?float => $v === null ? null : (float) $v;
    return ['type' => 'excursion', 'name' => $name, 'cert' => $cert, 'special' => (bool) $row['is_special_price'],
            'prices' => [$num($row['price_1_dive']), $num($row['price_2_dives']), $num($row['price_3_dives'])]];
}

/** Path of the cached PNG for a name like "excursion-dos-ojos-en" or "home-es"; rendered if absent. */
function og_file(string $name): ?string
{
    if (preg_match('/^(home|course|excursion)(?:-([a-z0-9-]+))?-(en|es)$/', $name, $m) !== 1) {
        return null;
    }
    [, $type, $slug, $lang] = $m;
    $file = og_cache_dir() . '/' . $name . '.png';
    if (is_file($file)) {
        return $file;
    }

    if ($type === 'home') {
        $im = og_render_home($lang);
    } else {
        $table = $type === 'course' ? 'courses' : 'excursions';
        $stmt = db()->prepare("SELECT * FROM {$table} WHERE slug = :s AND is_published = 1");
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $im = og_render_item(og_item_from_row($type, $row, $lang), $lang);
    }

    if (!is_dir(og_cache_dir()) && !mkdir(og_cache_dir(), 0750, true) && !is_dir(og_cache_dir())) {
        return null;
    }
    imagepng($im, $file, 7);
    imagedestroy($im);

    return $file;
}

/** Drop cached previews so they rebuild with the new data. */
function og_invalidate(?string $type = null, ?string $slug = null): void
{
    $pattern = $type === null ? '*.png' : ($type . '-' . ($slug ?? '*') . '-*.png');
    foreach (glob(og_cache_dir() . '/' . $pattern) ?: [] as $f) {
        @unlink($f);
    }
    if ($type === null || $type === 'home') {
        foreach (glob(og_cache_dir() . '/home-*.png') ?: [] as $f) {
            @unlink($f);
        }
    }
}
