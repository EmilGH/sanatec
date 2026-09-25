<?php

declare(strict_types=1);

/** /passport/<public_id> — a diver's public passport, if they have switched it on. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Passport.php';
require_once __DIR__ . '/templates/ui/public.php';

$lang = normalize_lang($_GET['lang'] ?? 'en');
$pid = preg_replace('/[^a-f0-9-]/', '', (string) ($_GET['id'] ?? '')) ?? '';
$pp = $pid !== '' ? passport_public($pid) : null;

send_header('Content-Type: text/html; charset=utf-8');
send_header('X-Robots-Tag: noindex');
if ($pp === null) {
    http_response_code(404);
}
$title = ($pp ? $pp['name'] . ' · ' : '') . ($lang === 'es' ? 'Pasaporte de cenotes' : 'Cenote Passport') . ' · ' . setting('business_name');
$baseUrl = rtrim((string) cfg('base_url'), '/');
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#04263a">
<title><?= e($title) ?></title>
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:image" content="<?= e($baseUrl) ?>/og/home-<?= e($lang) ?>.png">
<link rel="icon" href="/favicon.ico">
<?php require __DIR__ . '/templates/styles.php'; ?>
<style>.st-stamps{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}.st-stamp{border:2px solid var(--logo-swoosh);border-radius:50%;aspect-ratio:1;display:grid;place-items:center;text-align:center;padding:12px;color:var(--ink);font:400 15px/1.2 var(--font-serif)}.st-stamp small{display:block;color:var(--muted);font:600 11px/1.4 var(--font-sans);letter-spacing:.08em;text-transform:uppercase;margin-top:4px}.st-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px}.st-gallery img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius-sm)}.st-passport{padding:0 16px}</style>
</head>
<body>
<?= ui_brand_defs('dark') ?>
<div class="st-root" data-theme="dark">
<?= ui_public_header($lang) ?>
<main class="st-wrap st-passport">
<?php if ($pp === null): ?>
  <section class="st-section"><h1 class="st-h1"><?= $lang === 'es' ? 'Este pasaporte no está disponible.' : 'This passport is not available.' ?></h1><p class="st-lede"><?= $lang === 'es' ? 'Puede ser privado o el enlace no es correcto.' : 'It may be private, or the link is not right.' ?></p></section>
<?php else: ?>
  <section class="st-section">
    <p class="st-eyebrow"><?= $lang === 'es' ? 'Pasaporte de exploración de cenotes' : 'CENOTE Exploration Passport' ?></p>
    <h1 class="st-display"><?= e($pp['name']) ?><em><?= count($pp['dives']) ?> <?= $lang === 'es' ? 'buceos' : 'dives' ?> · <?= count($pp['stamps']) ?> <?= $lang === 'es' ? 'cenotes' : 'cenotes' ?></em></h1>
  </section>
  <section class="st-section">
    <div class="st-section__head"><p class="st-eyebrow"><?= $lang === 'es' ? 'Sellos' : 'Stamps' ?></p></div>
    <div class="st-stamps">
      <?php foreach ($pp['stamps'] as $s): ?><div class="st-stamp"><span><?= e($lang === 'es' ? $s['name_es'] : $s['name_en']) ?><small><?= e(date('M Y', strtotime($s['first_on']))) ?><?= (int) $s['dives'] > 1 ? ' · ×' . (int) $s['dives'] : '' ?></small></span></div><?php endforeach; ?>
    </div>
  </section>
  <?php if ($pp['photos'] !== []): ?>
  <section class="st-section">
    <div class="st-section__head"><p class="st-eyebrow"><?= $lang === 'es' ? 'Fotos' : 'Photos' ?></p></div>
    <div class="st-gallery"><?php foreach ($pp['photos'] as $ph): ?><img src="/passport-photo.php?id=<?= (int) $ph['id'] ?>" alt="<?= e((string) $ph['caption'] ?: $ph['site_en']) ?>" loading="lazy"><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
  <section class="st-section"><p class="st-lede"><?= $lang === 'es' ? 'Explorado con' : 'Explored with' ?> <a class="st-link" href="<?= $lang === 'es' ? '/es/' : '/' ?>"><?= e(setting('business_name')) ?></a>, Tulum.</p></section>
<?php endif; ?>
</main>
<?= ui_public_footer($lang) ?>
</div>
</body>
</html>
