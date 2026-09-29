<?php

declare(strict_types=1);

/**
 * /c/<slug> — one course or cenote route on its own page: photos, the
 * introduction and description, prices, the cenotes on the route, what is
 * included, and a WhatsApp button that already names the item.
 */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Photos.php';
require_once __DIR__ . '/src/Passport.php';
require_once __DIR__ . '/src/Affiliates.php';
require_once __DIR__ . '/templates/ui/public.php';

$lang = normalize_lang($_GET['lang'] ?? 'en');
$slug = strtolower(preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['slug'] ?? '')) ?? '');
$share = null;
if ($slug !== '') {
    if (($row = catalog_find_by_slug('excursions', $slug)) !== null) {
        $share = ['type' => 'excursion', 'row' => $row];
    } elseif (($row = catalog_find_by_slug('courses', $slug)) !== null) {
        $share = ['type' => 'course', 'row' => $row];
    }
}
if ($share === null) {
    send_header('Location: ' . LANGUAGES[$lang]['path']);
    exit;
}
$affiliate = affiliate_capture();
$r = $share['row'];
$type = $share['type'];
$photos = catalog_photos($type, (int) $r['id']);
$share['photo'] = $photos[0] ?? null;
$name = ui_name($r, $lang);
$prefix = $lang === 'es' ? '/es' : '';
$baseUrl = rtrim((string) cfg('base_url', 'https://sanatecdiving.com'), '/');
$intro = trim((string) ($r['intro_' . $lang] ?? '')) ?: (string) ($r['intro_en'] ?? '');
$body = trim((string) ($r['body_' . $lang] ?? '')) ?: (string) ($r['body_en'] ?? '');
$sites = $type === 'excursion' ? excursion_sites((int) $r['id']) : [];
$courses = [];
$excursions = [];

send_header('Content-Type: text/html; charset=utf-8');
send_header('Content-Language: ' . $lang);
send_header('Cache-Control: public, max-age=300');
send_header('X-Content-Type-Options: nosniff');
send_header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<?php require __DIR__ . '/templates/head.php'; ?>
<?php require __DIR__ . '/templates/styles.php'; ?>
<style>
.st-item{padding:0 16px}
.st-item__hero{position:relative;border-radius:var(--radius-lg);overflow:hidden;background:var(--panel);aspect-ratio:3/2;margin:8px 0 18px}
.st-item__hero img{width:100%;height:100%;object-fit:cover;display:block}
.st-item__gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin:12px 0 4px}
.st-item__gallery img{width:100%;aspect-ratio:3/2;object-fit:cover;border-radius:var(--radius-sm);display:block}
.st-item__body{font-size:17px;line-height:1.6;color:var(--ink);white-space:pre-line}
.st-prices{display:grid;gap:8px;margin:16px 0}
.st-price{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--line);border-radius:var(--radius-md);color:var(--ink);text-decoration:none;font-weight:600}
.st-price:hover{border-color:var(--aqua)}
.st-price b{font:400 22px/1 var(--font-serif)}
.st-facts{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0}
.st-site{display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-top:1px solid var(--line)}
.st-site img{width:96px;aspect-ratio:3/2;object-fit:cover;border-radius:var(--radius-sm);flex:none}
.st-site h3{margin:0;font:400 19px/1.2 var(--font-serif)}
.st-site p{margin:4px 0 0;color:var(--muted);font-size:14px;line-height:1.5}
.st-back{display:inline-flex;align-items:center;gap:6px;color:var(--aqua);text-decoration:none;font-weight:600;margin-top:8px}
@container (min-width:900px){.st-item__hero{aspect-ratio:21/9}.st-item__cols{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:48px}}
</style>
</head>
<body>
<?= ui_brand_defs('dark') ?>
<div class="st-root" data-theme="dark">
<?= ui_public_header($lang) ?>
<?= ui_affiliate_strip($affiliate, $lang) ?>
<main class="st-wrap st-item" id="main">
  <a class="st-back" href="<?= e($prefix ?: '/') ?>#<?= $type === 'course' ? 'training' : 'adventures' ?>"><?= ui_icon('back') ?><?= e(t($type === 'course' ? 'nav_training' : 'nav_adventures', $lang)) ?></a>
  <?php if ($photos !== []): ?>
  <div class="st-item__hero"><img src="<?= e(photo_url($photos[0])) ?>" alt="<?= e((string) ($photos[0]['caption_' . $lang] ?? $photos[0]['caption_en'] ?? $name)) ?>" fetchpriority="high"></div>
  <?php endif; ?>
  <section class="st-section" style="padding-top:8px">
    <p class="st-eyebrow"><?= e($type === 'course' ? t('item_course', $lang) . ' · ' . ui_name($r, $lang, 'duration') : t('item_excursion', $lang) . ' · ' . ui_name($r, $lang, 'cert')) ?></p>
    <h1 class="st-display" style="margin-top:4px"><?= e($name) ?><?= $intro !== '' ? '<em>' . e($intro) . '</em>' : '' ?></h1>
    <div class="st-item__cols">
      <div>
        <?php if ($body !== ''): ?><p class="st-item__body"><?= e($body) ?></p><?php endif; ?>
        <?php if ($type === 'course' && trim((string) ($r['prereq_' . $lang] ?? $r['prereq_en'] ?? '')) !== ''): ?>
          <p class="st-note"><strong><?= e(t('item_prereq', $lang)) ?>:</strong> <?= e(trim((string) ($r['prereq_' . $lang] ?? '')) ?: (string) $r['prereq_en']) ?></p>
        <?php endif; ?>
        <?php if (count($photos) > 1): ?>
        <div class="st-item__gallery"><?php foreach (array_slice($photos, 1) as $ph): ?><img src="<?= e(photo_url($ph, true)) ?>" alt="<?= e((string) ($ph['caption_' . $lang] ?? $ph['caption_en'] ?? '')) ?>" loading="lazy"><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($sites !== []): ?>
        <h2 class="st-h3 mt-4 mb-1"><?= e(t('item_cenotes', $lang)) ?></h2>
        <?php foreach ($sites as $site): $sp = catalog_photo_first('dive_site', (int) $site['id']); $desc = trim((string) ($site['description_' . $lang] ?? '')) ?: (string) ($site['description_en'] ?? ''); ?>
        <div class="st-site">
          <?php if ($sp): ?><img src="<?= e(photo_url($sp, true)) ?>" alt="" loading="lazy"><?php endif; ?>
          <div><h3><?= e($lang === 'es' ? $site['name_es'] : $site['name_en']) ?></h3>
            <p><?= $site['max_depth_m'] ? e(t('item_depth', $lang)) . ' ' . (int) $site['max_depth_m'] . ' m' : '' ?><?= $site['max_depth_m'] && $desc !== '' ? ' · ' : '' ?><?= e($desc) ?></p></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="st-prices">
          <?php if ($type === 'course'): $prefill = strtr(t('wa_line_course', $lang), ['{name}' => $name, '{duration}' => ui_name($r, $lang, 'duration')]); ?>
            <a class="st-price" href="<?= e(ui_wa_url($prefill)) ?>"><span><?= e(ui_name($r, $lang, 'duration')) ?></span><b><?= $r['price_mxn'] !== null ? e(money($r['price_mxn'])) . ' <small>MXN</small>' : e(t('ask_for_pricing', $lang)) ?></b></a>
          <?php else: foreach ([1 => 'price_1_dive', 2 => 'price_2_dives', 3 => 'price_3_dives'] as $n => $col): if ($r[$col] === null) { continue; }
            $qty = strtr(t($n === 1 ? 'dive_n' : 'dives_n', $lang), ['{n}' => (string) $n]);
            $prefill = strtr(t('wa_line_excursion', $lang), ['{qty}' => $qty, '{name}' => $name]); ?>
            <a class="st-price" href="<?= e(ui_wa_url($prefill)) ?>"><span><?= e($qty) ?></span><b><?= e(money($r[$col])) ?> <small>MXN</small></b></a>
          <?php endforeach; endif; ?>
        </div>
        <p class="st-note"><?= e($type === 'course' ? t('courses_mxn', $lang) : t('per_diver_mxn', $lang)) ?></p>
        <div class="st-actions"><a class="st-btn st-btn--primary" href="<?= e(ui_wa_url(strtr(t('wa_line_item', $lang), ['{name}' => $name]))) ?>"><?= ui_icon('whatsapp') ?><?= e(t('item_book', $lang)) ?></a></div>
        <?php if (setting('included_publish') === '1' && setting_lines('included_items', $lang) !== []): ?>
        <h2 class="st-h3 st-included__h mt-4"><?= e(setting('included_title', $lang)) ?></h2>
        <ul class="st-list st-list--in"><?php foreach (setting_lines('included_items', $lang) as $i) { echo '<li>', ui_icon('check'), e($i), '</li>'; } ?></ul>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?= ui_public_footer($lang) ?>
<?= ui_ctabar($lang) ?>
</div>
</body>
</html>
