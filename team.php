<?php

declare(strict_types=1);

/** /team/ and /team/<slug> — the people, for divers choosing who to dive with. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Team.php';
require_once __DIR__ . '/templates/ui/public.php';

$lang = normalize_lang($_GET['lang'] ?? 'en');
$slug = preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['slug'] ?? '')) ?? '';
$team = team_public_list($slug !== '' ? $slug : null);
$one = $slug !== '' ? ($team[0] ?? null) : null;
$prefix = $lang === 'es' ? '/es' : '';
$baseUrl = rtrim((string) cfg('base_url'), '/');

send_header('Content-Type: text/html; charset=utf-8');
if ($slug !== '' && $one === null) {
    http_response_code(404);
}
$title = ($one ? $one['name'] . ' · ' : '') . t('team_title', $lang) . ' · ' . setting('business_name');
$desc = $one ? trim((string) ($one['bio_' . $lang] ?: $one['bio_en'])) : t('team_intro', $lang);

$roleLine = static function (array $m) use ($lang): string {
    $roles = [];
    if ($m['is_instructor']) { $roles[] = t('role_instructor', $lang); }
    if ($m['is_divemaster']) { $roles[] = t('role_divemaster', $lang); }
    if ($m['is_cave_guide']) { $roles[] = t('role_cave_guide', $lang); }
    $title = trim((string) ($m['title_' . $lang] ?: $m['title_en']));

    return $title !== '' ? $title : implode(' · ', $roles);
};
$langNames = ['en' => 'English', 'es' => 'Español', 'de' => 'Deutsch', 'fr' => 'Français', 'it' => 'Italiano', 'pt' => 'Português', 'nl' => 'Nederlands', 'ru' => 'Русский'];
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#04263a">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(mb_substr($desc, 0, 160)) ?>">
<link rel="canonical" href="<?= e($baseUrl . $prefix . '/team/' . ($one ? $one['public_slug'] : '')) ?>">
<?php foreach (LANGUAGES as $code => $meta): ?><link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($baseUrl . ($code === 'es' ? '/es' : '') . '/team/' . ($one ? $one['public_slug'] : '')) ?>">
<?php endforeach; ?>
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e(mb_substr($desc, 0, 200)) ?>">
<meta property="og:image" content="<?= e($one && $one['photo_path'] ? $baseUrl . '/team/' . $one['public_slug'] . '/photo.jpg' : $baseUrl . '/og/home-' . $lang . '.png') ?>">
<link rel="icon" href="/favicon.ico">
<?php require __DIR__ . '/templates/styles.php'; ?>
<style>
.st-team{padding:0 16px}
.st-people{display:grid;gap:12px}
.st-person{display:flex;gap:14px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:var(--radius-md);background:var(--surface);color:var(--ink);text-decoration:none}
.st-person:hover{border-color:var(--aqua)}
.st-avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;flex:none;background:var(--surface-2)}
.st-avatar--big{width:160px;height:160px}
.st-person__t{font:400 20px/1.2 var(--font-serif)}
.st-person__s{display:block;color:var(--muted);font-size:14px;margin-top:2px}
.st-profile{display:grid;gap:20px}
.st-profile__bio{font-size:17px;line-height:1.6;color:var(--ink);white-space:pre-line}
.st-tags{display:flex;flex-wrap:wrap;gap:6px}
@container (min-width:720px){.st-people{grid-template-columns:1fr 1fr}.st-profile{grid-template-columns:200px 1fr;align-items:start}}
</style>
</head>
<body>
<?= ui_brand_defs('dark') ?>
<div class="st-root" data-theme="dark">
<?= ui_public_header($lang) ?>
<main class="st-wrap st-team">
<?php if ($slug !== '' && $one === null): ?>
  <section class="st-section"><h1 class="st-h1"><?= e(t('team_notfound', $lang)) ?></h1><p class="st-lede"><a class="st-link" href="<?= e($prefix) ?>/team/"><?= e(t('team_all', $lang)) ?></a></p></section>

<?php elseif ($one !== null): $m = $one; ?>
  <section class="st-section">
    <p class="st-eyebrow"><a class="st-link" href="<?= e($prefix) ?>/team/">← <?= e(t('team_all', $lang)) ?></a></p>
    <div class="st-profile">
      <?php if ($m['photo_path']): ?><img class="st-avatar st-avatar--big" src="/team/<?= e($m['public_slug']) ?>/photo.jpg" alt="<?= e($m['name']) ?>" width="160" height="160"><?php endif; ?>
      <div>
        <h1 class="st-display" style="margin-top:0"><?= e($m['name']) ?><em><?= e($roleLine($m)) ?></em></h1>
        <?php if ($m['credentials'] !== []): ?><div class="st-tags" style="margin:10px 0"><?php foreach ($m['credentials'] as $c): ?><span class="st-pill st-pill--neutral"><?= e($c) ?></span><?php endforeach; ?></div><?php endif; ?>
        <?php if ($m['languages'] !== []): ?><p class="st-note" style="margin-top:6px"><?= e(t('team_speaks', $lang)) ?> <?= e(implode(', ', array_map(static fn (string $c): string => $langNames[$c] ?? strtoupper($c), $m['languages']))) ?></p><?php endif; ?>
        <?php if (trim((string) ($m['bio_' . $lang] ?: $m['bio_en'])) !== ''): ?><p class="st-profile__bio" style="margin-top:16px"><?= e(trim((string) ($m['bio_' . $lang] ?: $m['bio_en']))) ?></p><?php endif; ?>
        <?php if ($m['whatsapp']): ?>
        <div class="st-actions"><a class="st-btn st-btn--primary" href="https://wa.me/<?= e(ltrim($m['whatsapp'], '+')) ?>?text=<?= rawurlencode(t('team_wa_prefill', $lang)) ?>" rel="noopener"><?= ui_icon('whatsapp') ?><?= e(t('team_message', $lang)) ?></a></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php else: ?>
  <section class="st-section">
    <p class="st-eyebrow"><?= e(t('team_kicker', $lang)) ?></p>
    <h1 class="st-h1"><?= e(t('team_title', $lang)) ?></h1>
    <p class="st-lede"><?= e(t('team_intro', $lang)) ?></p>
  </section>
  <section class="st-section" style="padding-top:0">
    <?php if ($team === []): ?><p class="st-note"><?= e(t('team_none', $lang)) ?></p><?php endif; ?>
    <div class="st-people">
      <?php foreach ($team as $m): ?>
      <a class="st-person" href="<?= e($prefix) ?>/team/<?= e($m['public_slug']) ?>">
        <?php if ($m['photo_path']): ?><img class="st-avatar" src="/team/<?= e($m['public_slug']) ?>/photo.jpg" alt="" width="64" height="64" loading="lazy"><?php else: ?><span class="st-avatar"></span><?php endif; ?>
        <span><span class="st-person__t"><?= e($m['name']) ?></span><span class="st-person__s"><?= e($roleLine($m)) ?><?= $m['languages'] !== [] ? ' · ' . e(strtoupper(implode(' ', $m['languages']))) : '' ?></span></span>
        <?= ui_icon('chevron') ?>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
</main>
<?= ui_public_footer($lang) ?>
<?= ui_ctabar($lang) ?>
</div>
</body>
</html>
